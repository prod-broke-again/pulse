<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\UploadAvatarRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Auth\PulseStaffAccess;
use App\Services\Auth\TokenIssueService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function __construct(
        private readonly PulseStaffAccess $pulseStaffAccess,
        private readonly TokenIssueService $tokenIssueService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        if (config('pulse.sso_only')) {
            throw new AuthorizationException(__('Password login is disabled. Use ACHPP ID SSO.'));
        }

        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $this->pulseStaffAccess->ensureStaff($user);

        $token = $this->tokenIssueService->issueSanctumToken(
            $user,
            $request->validated('device_name'),
            $request,
        );

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $user->currentAccessToken()->delete();

        return response()->json([
            'data' => [
                'message' => 'Logged out successfully.',
            ],
        ]);
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $file = $request->file('avatar');
        $extension = $file->getClientOriginalExtension() !== ''
            ? $file->getClientOriginalExtension()
            : ($file->guessExtension() ?? 'jpg');

        $filename = Str::uuid()->toString().'.'.$extension;

        $this->deletePreviousLocalAvatarIfPresent($user);

        $dir = public_path('avatars/'.$user->id);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file->move($dir, $filename);

        $publicUrl = url('avatars/'.$user->id.'/'.$filename);

        $user->forceFill(['avatar_url' => $publicUrl])->save();

        return response()->json([
            'data' => new UserResource($user->fresh() ?? $user),
        ], 200);
    }

    private function deletePreviousLocalAvatarIfPresent(User $user): void
    {
        $url = $user->avatar_url;
        if ($url === null || $url === '') {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/avatars/')) {
            return;
        }

        $relative = ltrim(str_replace('\\', '/', $path), '/');
        $full = public_path($relative);

        $avatarsRoot = realpath(public_path('avatars'));
        $candidate = realpath($full);
        if ($avatarsRoot === false || $candidate === false || ! is_file($candidate)) {
            return;
        }

        if (! str_starts_with($candidate, $avatarsRoot)) {
            return;
        }

        @unlink($candidate);
    }
}

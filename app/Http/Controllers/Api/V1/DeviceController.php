<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\RegisterDeviceRequest;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class DeviceController extends Controller
{
    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $validated = $request->validated();

        $device = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user->id,
                'platform' => $validated['platform'],
                'last_seen_at' => now(),
            ],
        );

        return response()->json([
            'data' => [
                'id' => $device->id,
                'token' => $device->token,
                'platform' => $device->platform,
            ],
        ], 201);
    }

    public function destroy(string $token): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        DeviceToken::where('user_id', $user->id)
            ->where('token', $token)
            ->delete();

        return response()->json([
            'data' => ['message' => 'Device token removed.'],
        ]);
    }
}

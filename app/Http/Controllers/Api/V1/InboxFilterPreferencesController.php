<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\InboxFilterPreferencesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class InboxFilterPreferencesController extends Controller
{
    public function __construct(
        private readonly InboxFilterPreferencesService $prefs,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureStaff($user);

        return response()->json([
            'data' => [
                'inbox_filter_prefs' => $this->prefs->forUser($user),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureStaff($user);

        $incoming = $request->all();
        $normalized = $this->prefs->validateAndNormalize($user, is_array($incoming) ? $incoming : []);

        $user->forceFill(['inbox_filter_prefs' => $normalized])->save();

        return response()->json([
            'data' => [
                'inbox_filter_prefs' => $this->prefs->forUser($user->fresh() ?? $user),
                'user' => new UserResource($user->fresh() ?? $user),
            ],
        ]);
    }

    private function ensureStaff(User $user): void
    {
        if (! $user->hasAnyRole(['admin', 'moderator'])) {
            abort(403);
        }
    }
}

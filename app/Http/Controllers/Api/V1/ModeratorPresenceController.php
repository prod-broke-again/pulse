<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\ModeratorPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class ModeratorPresenceController extends Controller
{
    public function __construct(
        private readonly ModeratorPresenceService $presenceService,
    ) {}

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureModeratorStaff($user);

        return response()->json([
            'data' => $this->presenceService->stateForUser($user),
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureModeratorStaff($user);

        $validated = $request->validate([
            'online' => ['required', 'boolean'],
        ]);

        $this->presenceService->setManualOnline($user, $validated['online']);

        return response()->json([
            'data' => $this->presenceService->stateForUser($user),
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureModeratorStaff($user);

        $this->presenceService->recordHeartbeat($user);

        return response()->json([
            'data' => $this->presenceService->stateForUser($user),
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureModeratorStaff($user);

        $this->presenceService->recordActivity($user);

        return response()->json([
            'data' => $this->presenceService->stateForUser($user),
        ]);
    }

    private function ensureModeratorStaff(User $user): void
    {
        if (! $user->hasAnyRole(['admin', 'moderator'])) {
            abort(403);
        }
    }
}

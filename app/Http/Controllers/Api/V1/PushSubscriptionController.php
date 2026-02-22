<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StorePushSubscriptionRequest;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $validated = $request->validated();

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => $user->id,
                'public_key' => $validated['keys']['p256dh'] ?? null,
                'auth_token' => $validated['keys']['auth'] ?? null,
                'user_agent' => $validated['user_agent'] ?? $request->userAgent(),
                'last_seen_at' => now(),
            ],
        );

        return response()->json([
            'data' => [
                'id' => $subscription->id,
                'endpoint' => $subscription->endpoint,
            ],
        ], 201);
    }
}

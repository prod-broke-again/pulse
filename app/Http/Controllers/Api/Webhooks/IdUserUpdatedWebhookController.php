<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhooks;

use App\Domains\Identity\DTOs\IdUserProfileDto;
use App\Http\Controllers\Controller;
use App\Services\Identity\UserUpsertFromIdService;
use App\Services\Webhooks\IdWebhookIdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class IdUserUpdatedWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        IdWebhookIdempotencyService $idempotency,
        UserUpsertFromIdService $upsert,
    ): JsonResponse {
        $idempotencyKey = (string) $request->header('X-Idempotency-Key', '');
        if ($idempotencyKey !== '' && $idempotency->isDuplicate($idempotencyKey, 'id.user-updated')) {
            return response()->json(['data' => ['message' => 'Already processed.']], 200);
        }

        /** @var array<string, mixed> $data */
        $data = $request->json()->all();
        $uuid = isset($data['id_user_uuid']) ? (string) $data['id_user_uuid'] : '';

        if ($uuid === '') {
            return response()->json([
                'message' => 'id_user_uuid is required.',
                'code' => 'VALIDATION_ERROR',
            ], 422);
        }

        $name = isset($data['name']) ? (string) $data['name'] : '';
        $email = isset($data['email']) ? (string) $data['email'] : '';
        $avatarUrl = isset($data['avatar_url']) ? ($data['avatar_url'] !== null ? (string) $data['avatar_url'] : null) : null;

        if ($name === '' || $email === '') {
            return response()->json([
                'message' => 'name and email are required.',
                'code' => 'VALIDATION_ERROR',
            ], 422);
        }

        $dto = new IdUserProfileDto(
            id: $uuid,
            name: $name,
            email: $email,
            avatarUrl: $avatarUrl,
        );

        $upsert->upsert($dto);

        Log::info('pulse.webhook.id_user_updated', ['id_user_uuid' => $uuid]);

        return response()->json([
            'data' => ['message' => 'OK'],
        ]);
    }
}

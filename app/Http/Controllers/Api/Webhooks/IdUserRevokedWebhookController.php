<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Webhooks\IdWebhookIdempotencyService;
use App\Services\Webhooks\RevokePulseSanctumTokensByIdUserUuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class IdUserRevokedWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        IdWebhookIdempotencyService $idempotency,
        RevokePulseSanctumTokensByIdUserUuid $revoke,
    ): JsonResponse {
        $idempotencyKey = (string) $request->header('X-Idempotency-Key', '');
        if ($idempotencyKey !== '' && $idempotency->isDuplicate($idempotencyKey, 'id.user-revoked')) {
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

        $revoke->handle($uuid);

        Log::info('pulse.webhook.id_user_revoked', ['id_user_uuid' => $uuid]);

        return response()->json([
            'data' => ['message' => 'OK'],
        ]);
    }
}

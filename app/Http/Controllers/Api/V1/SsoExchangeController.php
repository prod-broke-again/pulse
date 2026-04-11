<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PulseSsoExchangeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SsoExchangeRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\SsoExchangeService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

final class SsoExchangeController extends Controller
{
    public function __construct(
        private readonly SsoExchangeService $ssoExchangeService,
    ) {}

    public function exchange(SsoExchangeRequest $request): JsonResponse
    {
        try {
            $result = $this->ssoExchangeService->exchange($request);
        } catch (PulseSsoExchangeException $e) {
            $status = $e->httpStatus();

            Log::warning('pulse.auth.sso_exchange_failed', [
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
                'status' => $status,
                'previous' => $e->getPrevious()?->getMessage(),
                'idp' => $e->idpError,
            ]);

            $payload = [
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ];

            if ($e->idpError !== null) {
                $payload['idp'] = $e->idpError;
            }

            if (config('app.debug') && $e->getPrevious() !== null) {
                $payload['detail'] = $e->getPrevious()->getMessage();
            }

            return response()->json($payload, $status);
        } catch (AuthenticationException $e) {
            Log::notice('pulse.auth.sso_exchange_unauthenticated', [
                'message' => $e->getMessage(),
            ]);

            $message = $e->getMessage();

            return response()->json([
                'message' => $message !== '' ? $message : 'Unauthenticated.',
                'code' => $message !== '' ? 'AUTHENTICATION_FAILED' : 'UNAUTHENTICATED',
            ], 401);
        }

        Log::info('pulse.auth.sso_exchange_success', [
            'user_id' => $result['user']->id,
        ]);

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'user' => new UserResource($result['user']),
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\PulseSsoExchangeException;
use App\Models\User;
use App\Services\Identity\IdIdentityClient;
use App\Services\Identity\UserUpsertFromIdService;
use Illuminate\Http\Request;

final class SsoExchangeService
{
    public function __construct(
        private readonly IdIdentityClient $idIdentityClient,
        private readonly UserUpsertFromIdService $userUpsertFromIdService,
        private readonly PulseStaffAccess $pulseStaffAccess,
        private readonly TokenIssueService $tokenIssueService,
    ) {}

    /**
     * @return array{user: User, token: string}
     *
     * @throws PulseSsoExchangeException
     */
    public function exchange(Request $request): array
    {
        $accessToken = $request->input('access_token');
        if (! is_string($accessToken) || $accessToken === '') {
            $accessToken = $this->idIdentityClient->exchangeAuthorizationCodeForAccessToken(
                (string) $request->input('code'),
                (string) $request->input('code_verifier'),
                (string) $request->input('redirect_uri'),
            );
        }

        $profile = $this->idIdentityClient->fetchUserProfile($accessToken);
        $user = $this->userUpsertFromIdService->upsert($profile);

        $this->pulseStaffAccess->ensureStaff($user);

        $deviceName = $request->input('device_name');
        $token = $this->tokenIssueService->issueSanctumToken(
            $user,
            is_string($deviceName) ? $deviceName : null,
            $request,
        );

        return ['user' => $user, 'token' => $token];
    }
}

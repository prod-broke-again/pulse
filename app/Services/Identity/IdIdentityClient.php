<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Domains\Identity\DTOs\IdUserProfileDto;
use App\Exceptions\PulseSsoExchangeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class IdIdentityClient
{
    /**
     * Validates the access token and returns the IdP user profile.
     *
     * @throws PulseSsoExchangeException
     */
    public function fetchUserProfile(string $accessToken): IdUserProfileDto
    {
        $base = $this->resolveInternalBaseUrl();
        if ($base === null || $base === '') {
            throw new PulseSsoExchangeException(
                __('ACHPP ID base URL is not configured (ACHPP_ID_BASE_URL / ACHPP_ID_INTERNAL_URL).'),
                PulseSsoExchangeException::CODE_NOT_CONFIGURED,
            );
        }

        $path = (string) config('pulse.id.profile_path', '/api/v1/user');
        $fullUrl = rtrim((string) $base, '/').$path;

        $pending = IdHttpConfigurator::apply(
            Http::baseUrl($base)
                ->acceptJson()
                ->withToken($accessToken),
            $base,
        );

        try {
            $response = $pending->get($path);
        } catch (ConnectionException $e) {
            Log::error('pulse.id.profile_connection_failed', [
                'url' => $fullUrl,
                'exception' => $e->getMessage(),
            ]);
            throw new PulseSsoExchangeException(
                __('Unable to reach ACHPP ID for profile. Check ACHPP_ID_INTERNAL_URL/ACHPP_ID_BASE_URL, network, and timeouts.'),
                PulseSsoExchangeException::CODE_CONNECTION_FAILED,
                $e,
            );
        }

        if ($response->status() === 401) {
            throw new PulseSsoExchangeException(
                __('Invalid or expired IdP access token.'),
                PulseSsoExchangeException::CODE_PROFILE_UNAUTHORIZED,
            );
        }

        if (! $response->successful()) {
            Log::warning('pulse.id.profile_request_failed', [
                'url' => $fullUrl,
                'status' => $response->status(),
                'body' => $this->truncateLogBody($response->body()),
            ]);
            throw new PulseSsoExchangeException(
                __('ACHPP ID rejected the profile request.'),
                PulseSsoExchangeException::CODE_PROFILE_REJECTED,
            );
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $response->json();
        /** @var array<string, mixed>|null $data */
        $data = is_array($payload) ? ($payload['data'] ?? null) : null;

        if (! is_array($data) || ($data['id'] ?? '') === '' || ($data['email'] ?? '') === '') {
            Log::warning('pulse.id.profile_invalid_shape', [
                'url' => $fullUrl,
                'json' => $payload,
            ]);
            throw new PulseSsoExchangeException(
                __('Invalid identity response from ACHPP ID.'),
                PulseSsoExchangeException::CODE_PROFILE_INVALID,
            );
        }

        return IdUserProfileDto::fromIdApiDataArray($data);
    }

    /**
     * Exchange authorization code + PKCE verifier for an access token (public client).
     *
     * @throws PulseSsoExchangeException
     */
    public function exchangeAuthorizationCodeForAccessToken(
        string $code,
        string $codeVerifier,
        string $redirectUri,
    ): string {
        $base = $this->resolveInternalBaseUrl();
        $clientId = (string) config('pulse.id.oauth_client_id', '');
        if ($base === '' || $clientId === '') {
            throw new PulseSsoExchangeException(
                __('ACHPP ID OAuth is not configured (ACHPP_ID_INTERNAL_URL/ACHPP_ID_BASE_URL / ACHPP_ID_CLIENT_ID).'),
                PulseSsoExchangeException::CODE_NOT_CONFIGURED,
            );
        }

        $tokenUrl = rtrim($base, '/').'/oauth/token';

        $pending = IdHttpConfigurator::apply(
            Http::asForm()
                ->acceptJson(),
            $base,
        );

        try {
            $response = $pending->post($tokenUrl, [
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'code' => $code,
                'code_verifier' => $codeVerifier,
            ]);
        } catch (ConnectionException $e) {
            Log::error('pulse.id.token_connection_failed', [
                'url' => $tokenUrl,
                'exception' => $e->getMessage(),
            ]);
            throw new PulseSsoExchangeException(
                __('Unable to reach ACHPP ID token endpoint. Check ACHPP_ID_INTERNAL_URL/ACHPP_ID_BASE_URL, network, and timeouts.'),
                PulseSsoExchangeException::CODE_CONNECTION_FAILED,
                $e,
            );
        }

        if ($response->status() === 401) {
            throw new PulseSsoExchangeException(
                __('Invalid or expired authorization code.'),
                PulseSsoExchangeException::CODE_TOKEN_UNAUTHORIZED,
            );
        }

        if (! $response->successful()) {
            $idpMeta = $this->parseOAuthTokenErrorBody($response->body());
            Log::warning('pulse.id.token_exchange_failed', [
                'url' => $tokenUrl,
                'status' => $response->status(),
                'body' => $this->truncateLogBody($response->body()),
                'idp' => $idpMeta,
            ]);

            [$message, $errorCode] = $this->mapOAuthTokenFailureToMessage($idpMeta, $response->status());

            throw new PulseSsoExchangeException(
                $message,
                $errorCode,
                null,
                $idpMeta,
            );
        }

        /** @var array<string, mixed>|null $json */
        $json = $response->json();
        $accessToken = is_array($json) ? ($json['access_token'] ?? null) : null;

        if (! is_string($accessToken) || $accessToken === '') {
            Log::warning('pulse.id.token_exchange_invalid', [
                'url' => $tokenUrl,
                'json' => $json,
            ]);
            throw new PulseSsoExchangeException(
                __('Invalid token response from ACHPP ID.'),
                PulseSsoExchangeException::CODE_TOKEN_INVALID,
            );
        }

        return $accessToken;
    }

    private function truncateLogBody(string $body): string
    {
        if (strlen($body) <= 2000) {
            return $body;
        }

        return substr($body, 0, 2000).'…';
    }

    /**
     * @return array{oauth_error?: string, oauth_error_description?: string, hint?: string}|null
     */
    private function parseOAuthTokenErrorBody(string $body): ?array
    {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return null;
        }

        $out = [];
        if (isset($decoded['error']) && is_string($decoded['error'])) {
            $out['oauth_error'] = $decoded['error'];
        }
        if (isset($decoded['error_description']) && is_string($decoded['error_description'])) {
            $out['oauth_error_description'] = $decoded['error_description'];
        }
        if (isset($decoded['hint']) && is_string($decoded['hint'])) {
            $out['hint'] = $decoded['hint'];
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  array<string, string>|null  $idpMeta
     * @return array{0: string, 1: string}
     */
    private function mapOAuthTokenFailureToMessage(?array $idpMeta, int $status): array
    {
        $oauthErr = $idpMeta['oauth_error'] ?? '';

        if ($oauthErr === 'invalid_grant') {
            $hint = $idpMeta['hint'] ?? '';
            $message = str_contains((string) $hint, 'revoked')
                ? __('Authorization code was already used or revoked. Open the login screen and sign in again.')
                : __('Invalid or expired authorization code. Check redirect URI matches the IdP client, then try again.');

            return [$message, PulseSsoExchangeException::CODE_INVALID_GRANT];
        }

        if ($oauthErr !== '') {
            return [
                __('ACHPP ID rejected the token request (:error).', ['error' => $oauthErr]),
                PulseSsoExchangeException::CODE_TOKEN_REJECTED,
            ];
        }

        if ($status === 401) {
            return [
                __('Invalid or expired authorization code.'),
                PulseSsoExchangeException::CODE_TOKEN_UNAUTHORIZED,
            ];
        }

        return [
            __('ACHPP ID rejected the authorization code exchange.'),
            PulseSsoExchangeException::CODE_TOKEN_REJECTED,
        ];
    }

    private function resolveInternalBaseUrl(): string
    {
        return (string) config('pulse.id.id_url_internal', config('pulse.id.id_url_public', ''));
    }
}

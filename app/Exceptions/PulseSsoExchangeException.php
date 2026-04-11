<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Structured failure for POST /api/v1/auth/sso/exchange (IdP token exchange / profile).
 */
final class PulseSsoExchangeException extends \Exception
{
    public const CODE_NOT_CONFIGURED = 'SSO_ID_NOT_CONFIGURED';

    public const CODE_TOKEN_UNAUTHORIZED = 'SSO_ID_TOKEN_UNAUTHORIZED';

    public const CODE_TOKEN_REJECTED = 'SSO_ID_TOKEN_REJECTED';

    /** OAuth2 token endpoint returned invalid_grant (code reused, expired, wrong redirect_uri, etc.) */
    public const CODE_INVALID_GRANT = 'SSO_ID_INVALID_GRANT';

    public const CODE_TOKEN_INVALID = 'SSO_ID_TOKEN_INVALID';

    public const CODE_PROFILE_UNAUTHORIZED = 'SSO_ID_PROFILE_UNAUTHORIZED';

    public const CODE_PROFILE_REJECTED = 'SSO_ID_PROFILE_REJECTED';

    public const CODE_PROFILE_INVALID = 'SSO_ID_PROFILE_INVALID';

    public const CODE_CONNECTION_FAILED = 'SSO_ID_CONNECTION_FAILED';

    /**
     * @param  array<string, string>|null  $idpError  Normalized OAuth error fields for API clients (oauth_error, …)
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        ?\Throwable $previous = null,
        public readonly ?array $idpError = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function httpStatus(): int
    {
        return $this->errorCode === self::CODE_CONNECTION_FAILED ? 503 : 401;
    }
}

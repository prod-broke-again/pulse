<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

/**
 * Snapshot of ACHPP ID user profile from GET /api/v1/user (data object).
 */
final readonly class IdUserProfileDto
{
    /**
     * @param  list<array{provider: string, provider_user_id: string}>|null  $socialAccounts  null if IdP did not include social_accounts (do not overwrite local links)
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public ?string $avatarUrl,
        public ?array $socialAccounts = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromIdApiDataArray(array $data): self
    {
        $id = isset($data['id']) ? (string) $data['id'] : '';
        $name = isset($data['name']) ? (string) $data['name'] : '';
        $email = isset($data['email']) ? (string) $data['email'] : '';

        $avatar = $data['avatar_sm'] ?? $data['avatar'] ?? null;
        $avatarUrl = $avatar !== null && $avatar !== '' ? (string) $avatar : null;

        $socialAccounts = null;
        if (array_key_exists('social_accounts', $data)) {
            $socialAccounts = self::normalizeSocialAccounts($data['social_accounts']);
        }

        return new self(
            id: $id,
            name: $name,
            email: $email,
            avatarUrl: $avatarUrl,
            socialAccounts: $socialAccounts,
        );
    }

    /**
     * @return list<array{provider: string, provider_user_id: string}>
     */
    public static function normalizeSocialAccounts(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $provider = isset($row['provider']) ? (string) $row['provider'] : '';
            $providerUserId = isset($row['provider_user_id']) ? (string) $row['provider_user_id'] : '';
            if ($provider !== '' && $providerUserId !== '') {
                $out[] = ['provider' => $provider, 'provider_user_id' => $providerUserId];
            }
        }

        return $out;
    }
}

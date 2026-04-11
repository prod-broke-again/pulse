<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

/**
 * Snapshot of ACHPP ID user profile from GET /api/v1/user (data object).
 */
final readonly class IdUserProfileDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public ?string $avatarUrl,
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

        return new self(
            id: $id,
            name: $name,
            email: $email,
            avatarUrl: $avatarUrl,
        );
    }
}

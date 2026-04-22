<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * @return array{name: string|null, avatar_url: string|null}
 */
final class BroadcastSenderDisplay
{
    public static function forMessage(?int $senderId, string $senderType): array
    {
        if ($senderId === null || $senderType !== 'moderator') {
            return ['name' => null, 'avatar_url' => null];
        }

        $user = User::query()
            ->select(['id', 'name', 'avatar_url'])
            ->find($senderId);
        if ($user === null) {
            return ['name' => null, 'avatar_url' => null];
        }

        $name = trim((string) $user->name);

        return [
            'name' => $name !== '' ? $name : 'Модератор',
            'avatar_url' => $user->avatar_url ? trim((string) $user->avatar_url) : null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Prefixes outgoing moderator text in non-private Telegram peers so end users
 * in groups/forums know which operator replied.
 */
final class TelegramGroupModeratorSignature
{
    public const METADATA_KEY_CHAT_TYPE = 'chat_type';

    /**
     * @param  array<string, mixed>  $userMetadata  Chat {@see Chat} user_metadata (merged guest meta + Telegram context).
     */
    public static function formatForOutbound(
        string $text,
        array $userMetadata,
        int $moderatorUserId,
    ): string {
        if (! self::isNonPrivateTelegramPeer($userMetadata)) {
            return $text;
        }

        $user = User::query()->find($moderatorUserId);
        $name = $user !== null ? trim((string) $user->name) : '';
        if ($name === '') {
            $name = 'Модератор';
        }

        return sprintf("%s отвечает:\n%s", $name, $text);
    }

    /**
     * @param  array<string, mixed>  $userMetadata
     */
    public static function isNonPrivateTelegramPeer(array $userMetadata): bool
    {
        $type = $userMetadata[self::METADATA_KEY_CHAT_TYPE] ?? null;
        if (! is_string($type) || $type === '') {
            return false;
        }

        return in_array($type, ['group', 'supergroup', 'channel'], true);
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use App\Domains\Communication\Entity\Chat;
use App\Infrastructure\Persistence\Eloquent\ChatModel;

/**
 * Merges Telegram Bot API business_connection_id for outbound sends (chat row, then source settings).
 */
final class TelegramOutboundBusinessOptions
{
    /**
     * @param  array<string, mixed>  $sourceSettings
     * @return array<string, string>
     */
    public static function mergeInto(array $options, ChatModel $chat, array $sourceSettings): array
    {
        foreach (self::fromChatModelAndSettings($chat, $sourceSettings) as $key => $value) {
            $options[$key] = $value;
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $sourceSettings
     * @return array<string, string>
     */
    public static function fromDomainChat(Chat $chat, array $sourceSettings): array
    {
        if ($chat->externalBusinessConnectionId !== null && $chat->externalBusinessConnectionId !== '') {
            return ['business_connection_id' => $chat->externalBusinessConnectionId];
        }

        $id = $sourceSettings['business_connection_id'] ?? null;
        if (is_string($id) && $id !== '') {
            return ['business_connection_id' => $id];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $sourceSettings
     * @return array<string, string>
     */
    public static function fromChatModelAndSettings(ChatModel $chat, array $sourceSettings): array
    {
        $cid = $chat->external_business_connection_id;
        if (is_string($cid) && $cid !== '') {
            return ['business_connection_id' => $cid];
        }

        $id = $sourceSettings['business_connection_id'] ?? null;
        if (is_string($id) && $id !== '') {
            return ['business_connection_id' => $id];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $sourceSettings
     * @return array<string, string>
     */
    public static function fromSourceSettingsOnly(array $sourceSettings): array
    {
        $id = $sourceSettings['business_connection_id'] ?? null;
        if (is_string($id) && $id !== '') {
            return ['business_connection_id' => $id];
        }

        return [];
    }
}

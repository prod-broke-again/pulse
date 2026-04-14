<?php

declare(strict_types=1);

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\MessageModel;
use Illuminate\Support\Str;

/**
 * Resolves quoted reply for API when {@see MessageModel::reply_to_id} is null
 * but the stored Telegram/VK webhook payload still contains reply metadata.
 */
final class InboundMessageReplyResolver
{
    /**
     * @return array{id: int|null, text: string, sender_type: string}|null
     */
    public static function resolveForApi(MessageModel $message): ?array
    {
        $message->loadMissing('replyTo');
        if ($message->replyTo !== null) {
            return [
                'id' => $message->replyTo->id,
                'text' => Str::limit((string) $message->replyTo->text, 280),
                'sender_type' => $message->replyTo->sender_type,
            ];
        }

        $payload = $message->payload;
        if (! is_array($payload) || ! self::payloadMightContainReply($payload)) {
            return null;
        }

        $parent = self::findParentMessageInChat($message->chat_id, $payload);
        if ($parent !== null) {
            return [
                'id' => $parent->id,
                'text' => Str::limit((string) $parent->text, 280),
                'sender_type' => $parent->sender_type,
            ];
        }

        return self::syntheticReplyFromMessengerPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function payloadMightContainReply(array $payload): bool
    {
        if (isset($payload['message']['reply_to_message']) && is_array($payload['message']['reply_to_message'])) {
            return true;
        }

        return isset($payload['object']['message']['reply_message'])
            && is_array($payload['object']['message']['reply_message']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function findParentMessageInChat(int $chatId, array $payload): ?MessageModel
    {
        $externalId = self::extractReplyToExternalMessageId($payload);
        if ($externalId === null || $externalId === '') {
            return null;
        }

        $base = MessageModel::query()->where('chat_id', $chatId);

        $byColumn = (clone $base)->where('external_message_id', $externalId)->first();
        if ($byColumn !== null) {
            return $byColumn;
        }

        $asInt = is_numeric($externalId) ? (int) $externalId : null;

        return (clone $base)
            ->where(function ($q) use ($externalId, $asInt) {
                $q->where('payload->message->message_id', $externalId);
                if ($asInt !== null) {
                    $q->orWhere('payload->message->message_id', $asInt);
                }
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * Same shapes as {@see \App\Application\Communication\Webhook\WebhookPayloadExtractor::extractReplyToExternalMessageId}
     * but reading from already-persisted JSON.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function extractReplyToExternalMessageId(array $payload): ?string
    {
        $containers = [
            $payload['message'] ?? null,
            $payload['edited_message'] ?? null,
            $payload['channel_post'] ?? null,
        ];
        if (isset($payload['callback_query']['message']) && is_array($payload['callback_query']['message'])) {
            $containers[] = $payload['callback_query']['message'];
        }
        foreach ($containers as $msg) {
            if (! is_array($msg)) {
                continue;
            }
            $reply = $msg['reply_to_message'] ?? null;
            if (is_array($reply) && isset($reply['message_id'])) {
                return (string) $reply['message_id'];
            }
        }

        $vkReply = $payload['object']['message']['reply_message'] ?? null;
        if (is_array($vkReply) && isset($vkReply['id'])) {
            return (string) $vkReply['id'];
        }

        return null;
    }

    /**
     * Show quote text from Telegram/VK when parent row cannot be linked (e.g. old messages without external_message_id).
     *
     * @param  array<string, mixed>  $payload
     * @return array{id: null, text: string, sender_type: string}
     */
    private static function syntheticReplyFromMessengerPayload(array $payload): ?array
    {
        $reply = null;
        $msg = $payload['message'] ?? null;
        if (is_array($msg) && isset($msg['reply_to_message']) && is_array($msg['reply_to_message'])) {
            $reply = $msg['reply_to_message'];
        }
        if ($reply === null) {
            $vk = $payload['object']['message']['reply_message'] ?? null;
            if (is_array($vk)) {
                $reply = $vk;
            }
        }
        if (! is_array($reply)) {
            return null;
        }

        $text = $reply['text'] ?? $reply['caption'] ?? '';
        $text = trim((string) $text);
        if ($text === '') {
            $text = '[Сообщение без текста]';
        }

        $isBot = $reply['from']['is_bot'] ?? false;
        $senderType = $isBot === true ? 'moderator' : 'client';

        return [
            'id' => null,
            'text' => Str::limit($text, 280),
            'sender_type' => $senderType,
        ];
    }
}

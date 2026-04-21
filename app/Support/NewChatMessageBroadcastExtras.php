<?php

declare(strict_types=1);

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\MessageModel;
use Illuminate\Support\Str;

/**
 * Builds optional broadcast payload fields from a persisted message.
 */
final class NewChatMessageBroadcastExtras
{
    /**
     * @return array{
     *     attachments: list<array<string, mixed>>,
     *     reply_to: ?array{id: int, text: string, sender_type: string},
     *     pending_attachments: list<array<string, mixed>>,
     *     delivery_channel: ?string,
     * }
     */
    public static function fromMessage(MessageModel $message): array
    {
        $message->loadMissing(['media', 'replyTo']);

        $attachments = $message->payload['attachments'] ?? [];
        if (! is_array($attachments)) {
            $attachments = [];
        }
        if ($attachments === [] && $message->relationLoaded('media')) {
            $attachments = $message->getMedia('attachments')->map(fn ($media) => [
                'id' => $media->id,
                'name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'url' => $media->getUrl(),
            ])->values()->all();
        }

        $pending = $message->payload['pending_attachments'] ?? [];
        if (! is_array($pending)) {
            $pending = [];
        }

        $replyTo = null;
        if ($message->relationLoaded('replyTo') && $message->replyTo !== null) {
            $replyTo = [
                'id' => $message->replyTo->id,
                'text' => Str::limit((string) $message->replyTo->text, 280),
                'sender_type' => $message->replyTo->sender_type,
            ];
        }

        $deliveryChannel = $message->payload['delivery_channel'] ?? null;
        if (! is_string($deliveryChannel) || $deliveryChannel === '') {
            $deliveryChannel = null;
        }

        return [
            'attachments' => $attachments,
            'reply_to' => $replyTo,
            'pending_attachments' => array_values($pending),
            'delivery_channel' => $deliveryChannel,
        ];
    }
}

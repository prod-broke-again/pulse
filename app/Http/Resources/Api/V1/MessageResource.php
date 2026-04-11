<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin \App\Infrastructure\Persistence\Eloquent\MessageModel */
final class MessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $attachments = $this->payload['attachments'] ?? [];

        if ($attachments === [] && $this->relationLoaded('media')) {
            $attachments = $this->getMedia('attachments')->map(fn ($media) => [
                'id' => $media->id,
                'name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'url' => $media->getUrl(),
            ])->values()->all();
        }

        return [
            'id' => $this->id,
            'chat_id' => $this->chat_id,
            'sender_id' => $this->sender_id,
            'sender_type' => $this->sender_type,
            'text' => $this->text,
            'payload' => $this->payload ?? [],
            'reply_markup' => $this->reply_markup,
            'attachments' => $attachments,
            'is_read' => $this->is_read,
            'reply_to' => $this->whenLoaded('replyTo', fn () => $this->replyTo ? [
                'id' => $this->replyTo->id,
                'text' => Str::limit((string) $this->replyTo->text, 280),
                'sender_type' => $this->replyTo->sender_type,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

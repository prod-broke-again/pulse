<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class NewChatMessage implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $attachments
     * @param  list<array<string, mixed>>  $pendingAttachments
     * @param  array{id: int, text: string, sender_type: string}|null  $replyTo
     */
    public function __construct(
        public int $chatId,
        public int $messageId,
        public string $text,
        public string $senderType = 'client',
        public ?int $senderId = null,
        public array $attachments = [],
        public array $pendingAttachments = [],
        public ?array $replyTo = null,
        public ?int $assignedModeratorUserId = null,
        public int $sourceId = 0,
        public bool $isNewChat = false,
        public ?string $deliveryChannel = null,
    ) {}

    /** @return array<int, Channel|PrivateChannel> */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('chat.'.$this->chatId),
            new Channel('widget-chat.'.$this->chatId),
        ];

        if ($this->sourceId > 0) {
            $channels[] = new PrivateChannel('source-inbox.'.$this->sourceId);
        }

        if ($this->assignedModeratorUserId !== null) {
            $channels[] = new PrivateChannel('moderator.'.$this->assignedModeratorUserId);
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'chatId' => $this->chatId,
            'messageId' => $this->messageId,
            'text' => $this->text,
            'sender_type' => $this->senderType,
            'sender_id' => $this->senderId,
            'attachments' => $this->attachments,
            'pending_attachments' => $this->pendingAttachments,
            'reply_to' => $this->replyTo,
            'source_id' => $this->sourceId > 0 ? $this->sourceId : null,
            'is_new_chat' => $this->isNewChat,
            'delivery_channel' => $this->deliveryChannel,
        ];
    }
}

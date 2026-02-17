<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UserTyping implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $chatId,
        public string $senderType,
        public ?string $senderName = null,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.'.$this->chatId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'chat_id' => $this->chatId,
            'sender_type' => $this->senderType,
            'sender_name' => $this->senderName,
        ];
    }
}

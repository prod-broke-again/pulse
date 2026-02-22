<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class NewChatMessage implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $chatId,
        public int $messageId,
        public string $text,
    ) {}

    /** @return array<int, Channel|PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->chatId),
            new Channel('widget-chat.' . $this->chatId),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'chatId' => $this->chatId,
            'messageId' => $this->messageId,
            'text' => $this->text,
        ];
    }
}

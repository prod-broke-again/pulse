<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ChatTopicGenerated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $chatId,
        public string $topic,
        public ?int $assignedModeratorUserId = null,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('chat.'.$this->chatId),
        ];

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
            'topic' => $this->topic,
            'assigned_moderator_user_id' => $this->assignedModeratorUserId,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Domains\Communication\Entity\Message;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\Repository\MessageRepositoryInterface;
use App\Domains\Communication\ValueObject\SenderType;
use App\Events\NewChatMessage as NewChatMessageEvent;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class CreateMessage
{
    public function __construct(
        private MessageRepositoryInterface $messageRepository,
        private ChatRepositoryInterface $chatRepository,
        private Dispatcher $events,
    ) {}

    public function run(
        int $chatId,
        string $text,
        SenderType $senderType,
        ?int $senderId = null,
        array $payload = [],
    ): Message {
        $chat = $this->chatRepository->findById($chatId);
        if ($chat === null) {
            throw new \InvalidArgumentException("Chat not found: {$chatId}");
        }

        $message = new \App\Domains\Communication\Entity\Message(
            id: 0,
            chatId: $chatId,
            senderId: $senderId,
            senderType: $senderType,
            text: $text,
            payload: $payload,
            isRead: false,
        );

        $persisted = $this->messageRepository->persist($message);

        $this->events->dispatch(new NewChatMessageEvent(
            chatId: $chatId,
            messageId: $persisted->id,
            text: $text,
        ));

        return $persisted;
    }
}

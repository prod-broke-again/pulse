<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Domains\Communication\Entity\Message;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\Repository\MessageRepositoryInterface;
use App\Domains\Communication\ValueObject\SenderType;
use App\Events\NewChatMessage as NewChatMessageEvent;
use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Domains\Integration\Repository\SourceRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class SendMessage
{
    public function __construct(
        private MessageRepositoryInterface $messageRepository,
        private ChatRepositoryInterface $chatRepository,
        private SourceRepositoryInterface $sourceRepository,
        private Dispatcher $events,
    ) {}

    public function run(
        int $chatId,
        string $text,
        SenderType $senderType,
        ?int $senderId = null,
        MessengerProviderInterface $messenger,
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

        $source = $this->sourceRepository->findById($chat->sourceId);
        if ($source !== null) {
            $messenger->sendMessage($chat->externalUserId, $text, ['message_id' => $persisted->id]);
        }

        return $persisted;
    }
}

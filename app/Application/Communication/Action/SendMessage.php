<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Domains\Communication\Entity\Chat;
use App\Domains\Communication\Entity\Message;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\Repository\MessageRepositoryInterface;
use App\Domains\Communication\ValueObject\ChatStatus;
use App\Domains\Communication\ValueObject\SenderType;
use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Domains\Integration\Repository\SourceRepositoryInterface;
use App\Events\ChatAssigned as ChatAssignedEvent;
use App\Events\NewChatMessage as NewChatMessageEvent;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Support\NewChatMessageBroadcastExtras;
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
        ?int $senderId,
        MessengerProviderInterface $messenger,
        array $payload = [],
        ?int $replyToMessageId = null,
        /** @var list<array{text: string, url: string}>|null */
        ?array $replyMarkup = null,
        bool $deliverToMessenger = true,
    ): Message {
        $chat = $this->chatRepository->findById($chatId);
        if ($chat === null) {
            throw new \InvalidArgumentException("Chat not found: {$chatId}");
        }

        if ($chat->assignedTo === null && $senderType === SenderType::Moderator && $senderId !== null) {
            $updated = new Chat(
                id: $chat->id,
                sourceId: $chat->sourceId,
                departmentId: $chat->departmentId,
                externalUserId: $chat->externalUserId,
                userMetadata: $chat->userMetadata,
                status: ChatStatus::Active,
                assignedTo: $senderId,
                topic: $chat->topic,
            );
            $chat = $this->chatRepository->persist($updated);
            $this->events->dispatch(new ChatAssignedEvent(chatId: $chat->id, assignedToUserId: $senderId));
        }

        $message = new \App\Domains\Communication\Entity\Message(
            id: 0,
            chatId: $chatId,
            externalMessageId: null,
            senderId: $senderId,
            senderType: $senderType,
            text: $text,
            payload: $payload,
            replyMarkup: $replyMarkup,
            isRead: false,
            replyToId: $replyToMessageId,
        );

        $persisted = $this->messageRepository->persist($message);

        $model = MessageModel::query()->with('replyTo')->find($persisted->id);
        $extras = $model !== null
            ? NewChatMessageBroadcastExtras::fromMessage($model)
            : ['attachments' => [], 'reply_to' => null];

        $this->events->dispatch(new NewChatMessageEvent(
            chatId: $chatId,
            messageId: $persisted->id,
            text: $text,
            senderType: $senderType->value,
            senderId: $senderId,
            attachments: $extras['attachments'],
            replyTo: $extras['reply_to'],
            assignedModeratorUserId: $chat->assignedTo,
        ));

        $source = $this->sourceRepository->findById($chat->sourceId);
        if ($source !== null && $deliverToMessenger) {
            $options = ['message_id' => $persisted->id];
            if ($persisted->replyMarkup !== null && $persisted->replyMarkup !== []) {
                $options['reply_markup'] = $persisted->replyMarkup;
            }
            if ($persisted->replyToId !== null) {
                $replySource = MessageModel::query()->find($persisted->replyToId);
                if ($replySource !== null
                    && $replySource->external_message_id !== null
                    && $replySource->external_message_id !== '') {
                    $options['reply_to_external_message_id'] = $replySource->external_message_id;
                }
            }
            $messenger->sendMessage($chat->externalUserId, $text, $options);
        }

        return $persisted;
    }
}

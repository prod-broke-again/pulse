<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Domains\Communication\Entity\Message;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\Repository\MessageRepositoryInterface;
use App\Domains\Communication\ValueObject\SenderType;
use App\Events\NewChatMessage as NewChatMessageEvent;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Support\NewChatMessageBroadcastExtras;
use App\Jobs\GenerateChatTopicJob;
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
        ?string $externalMessageId = null,
        ?int $replyToMessageId = null,
    ): Message {
        $chat = $this->chatRepository->findById($chatId);
        if ($chat === null) {
            throw new \InvalidArgumentException("Chat not found: {$chatId}");
        }

        if ($externalMessageId !== null) {
            $existing = $this->messageRepository->findByChatAndExternalMessageId($chatId, $externalMessageId);
            if ($existing !== null) {
                return $existing;
            }
        }

        $message = new \App\Domains\Communication\Entity\Message(
            id: 0,
            chatId: $chatId,
            externalMessageId: $externalMessageId,
            senderId: $senderId,
            senderType: $senderType,
            text: $text,
            payload: $payload,
            replyMarkup: null,
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

        if ($senderType === SenderType::Client) {
            $clientMessageCount = MessageModel::where('chat_id', $chatId)
                ->where('sender_type', 'client')
                ->count();
            $topicEmpty = $chat->topic === null || $chat->topic === '';
            if ($clientMessageCount >= 1 && $clientMessageCount <= 2 && $topicEmpty) {
                GenerateChatTopicJob::dispatch($chatId);
            }
        }

        return $persisted;
    }
}

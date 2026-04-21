<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Domains\Communication\Entity\Chat;
use App\Domains\Communication\Entity\Message;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\Repository\MessageRepositoryInterface;
use App\Domains\Communication\ValueObject\ChatStatus;
use App\Domains\Communication\ValueObject\SenderType;
use App\Events\ChatAssigned as ChatAssignedEvent;
use App\Events\NewChatMessage as NewChatMessageEvent;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Models\User;
use App\Support\NewChatMessageBroadcastExtras;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class AssignChatToModerator
{
    public function __construct(
        private ChatRepositoryInterface $chatRepository,
        private MessageRepositoryInterface $messageRepository,
        private Dispatcher $events,
    ) {}

    public function run(int $chatId, int $userId): Chat
    {
        $chat = $this->chatRepository->findById($chatId);
        if ($chat === null) {
            throw new \InvalidArgumentException("Chat not found: {$chatId}");
        }

        $previousAssignee = $chat->assignedTo;

        $updated = new Chat(
            id: $chat->id,
            sourceId: $chat->sourceId,
            departmentId: $chat->departmentId,
            externalUserId: $chat->externalUserId,
            userMetadata: $chat->userMetadata,
            status: ChatStatus::Active,
            assignedTo: $userId,
            topic: $chat->topic,
            externalBusinessConnectionId: $chat->externalBusinessConnectionId,
        );

        $persisted = $this->chatRepository->persist($updated);

        $this->events->dispatch(new ChatAssignedEvent(chatId: $persisted->id, assignedToUserId: $userId));

        if ($previousAssignee !== $userId) {
            $this->persistAssignmentSystemMessage($persisted->id, $userId, $persisted->sourceId);
        }

        return $persisted;
    }

    private function persistAssignmentSystemMessage(int $chatId, int $assignedToUserId, int $sourceId): void
    {
        $assignee = User::query()->find($assignedToUserId);
        $name = $assignee !== null ? trim((string) $assignee->name) : '';
        if ($name === '') {
            $name = 'Модератор';
        }
        $text = 'Чат передан модератору: '.$name;

        $domainMessage = new Message(
            id: 0,
            chatId: $chatId,
            externalMessageId: null,
            senderId: null,
            senderType: SenderType::System,
            text: $text,
            payload: [],
            replyMarkup: null,
            isRead: false,
            replyToId: null,
        );

        $persistedMessage = $this->messageRepository->persist($domainMessage);

        $model = MessageModel::query()->with('replyTo')->find($persistedMessage->id);
        $extras = $model !== null
            ? NewChatMessageBroadcastExtras::fromMessage($model)
            : [
                'attachments' => [],
                'reply_to' => null,
                'pending_attachments' => [],
                'delivery_channel' => null,
            ];

        $isNewChat = MessageModel::query()->where('chat_id', $chatId)->count() === 1;

        $this->events->dispatch(new NewChatMessageEvent(
            chatId: $chatId,
            messageId: $persistedMessage->id,
            text: $text,
            senderType: SenderType::System->value,
            senderId: null,
            attachments: $extras['attachments'],
            pendingAttachments: $extras['pending_attachments'],
            replyTo: $extras['reply_to'],
            assignedModeratorUserId: $assignedToUserId,
            sourceId: $sourceId,
            isNewChat: $isNewChat,
            deliveryChannel: $extras['delivery_channel'] ?? null,
        ));
    }
}

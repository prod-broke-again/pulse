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
use App\Domains\Integration\ValueObject\SourceType;
use App\Events\ChatAssigned as ChatAssignedEvent;
use App\Events\NewChatMessage as NewChatMessageEvent;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Support\BroadcastSenderDisplay;
use App\Support\NewChatMessageBroadcastExtras;
use App\Support\TelegramGroupModeratorSignature;
use App\Support\TelegramOutboundBusinessOptions;
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
            $chat = $this->chatRepository->persist($chat->withOverrides([
                'status' => ChatStatus::Active,
                'assignedTo' => $senderId,
            ]));
            $this->events->dispatch(new ChatAssignedEvent(chatId: $chat->id, assignedToUserId: $senderId));
        }

        $textToPersist = $this->maybePrefixTelegramGroupModeratorLine(
            $text,
            $chat,
            $senderType,
            $senderId,
            $deliverToMessenger,
        );

        $message = new \App\Domains\Communication\Entity\Message(
            id: 0,
            chatId: $chatId,
            externalMessageId: null,
            senderId: $senderId,
            senderType: $senderType,
            text: $textToPersist,
            payload: $payload,
            replyMarkup: $replyMarkup,
            isRead: false,
            replyToId: $replyToMessageId,
        );

        $persisted = $this->messageRepository->persist($message);
        $this->chatRepository->touchLastActivityAt($chatId);

        $model = MessageModel::query()->with('replyTo')->find($persisted->id);
        $extras = $model !== null
            ? NewChatMessageBroadcastExtras::fromMessage($model)
            : [
                'attachments' => [],
                'reply_to' => null,
                'pending_attachments' => [],
                'delivery_channel' => null,
            ];

        $isNewChat = MessageModel::query()->where('chat_id', $chatId)->count() === 1;
        $senderDisplay = BroadcastSenderDisplay::forMessage($senderId, $senderType->value);

        $this->events->dispatch(new NewChatMessageEvent(
            chatId: $chatId,
            messageId: $persisted->id,
            text: $textToPersist,
            senderType: $senderType->value,
            senderId: $senderId,
            attachments: $extras['attachments'],
            pendingAttachments: $extras['pending_attachments'],
            replyTo: $extras['reply_to'],
            assignedModeratorUserId: $chat->assignedTo,
            sourceId: $chat->sourceId,
            isNewChat: $isNewChat,
            deliveryChannel: $extras['delivery_channel'] ?? null,
            senderName: $senderDisplay['name'],
            senderAvatarUrl: $senderDisplay['avatar_url'],
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
            foreach (TelegramOutboundBusinessOptions::fromDomainChat($chat, $source->settings) as $key => $value) {
                $options[$key] = $value;
            }
            $messenger->sendMessage($chat->externalUserId, $textToPersist, $options);
        }

        return $persisted;
    }

    private function maybePrefixTelegramGroupModeratorLine(
        string $text,
        Chat $chat,
        SenderType $senderType,
        ?int $senderId,
        bool $deliverToMessenger,
    ): string {
        if (! $deliverToMessenger
            || $senderType !== SenderType::Moderator
            || $senderId === null) {
            return $text;
        }
        $source = $this->sourceRepository->findById($chat->sourceId);
        if ($source === null || $source->type !== SourceType::Tg) {
            return $text;
        }

        return TelegramGroupModeratorSignature::formatForOutbound(
            $text,
            $chat->userMetadata,
            $senderId,
        );
    }
}

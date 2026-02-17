<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domains\Communication\Entity\Message;
use App\Domains\Communication\Repository\MessageRepositoryInterface;
use App\Domains\Communication\ValueObject\SenderType;
use App\Infrastructure\Persistence\Eloquent\MessageModel;

final class EloquentMessageRepository implements MessageRepositoryInterface
{
    public function findById(int $id): ?Message
    {
        $model = MessageModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    /** @return list<Message> */
    public function listByChatId(int $chatId): array
    {
        return MessageModel::where('chat_id', $chatId)
            ->orderBy('id')
            ->get()
            ->map(fn (MessageModel $m) => $this->toEntity($m))
            ->values()
            ->all();
    }

    public function persist(Message $message): Message
    {
        $model = $message->id > 0
            ? MessageModel::findOrFail($message->id)
            : new MessageModel();

        $model->chat_id = $message->chatId;
        $model->sender_id = $message->senderId;
        $model->sender_type = $message->senderType->value;
        $model->text = $message->text;
        $model->payload = $message->payload;
        $model->is_read = $message->isRead;
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(MessageModel $model): Message
    {
        return new Message(
            id: $model->id,
            chatId: $model->chat_id,
            senderId: $model->sender_id,
            senderType: $model->getSenderTypeEnum(),
            text: $model->text,
            payload: $model->payload ?? [],
            isRead: $model->is_read,
        );
    }
}

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

    public function findByChatAndExternalMessageId(int $chatId, string $externalMessageId): ?Message
    {
        $model = MessageModel::where('chat_id', $chatId)
            ->where('external_message_id', $externalMessageId)
            ->first();

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

    /** @return list<Message> */
    public function listByChatIdPaginated(int $chatId, int $limit = 50, ?int $beforeId = null): array
    {
        $query = MessageModel::where('chat_id', $chatId);
        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }
        $models = $query->orderByDesc('id')->limit($limit)->get()->reverse()->values();

        return $models->map(fn (MessageModel $m) => $this->toEntity($m))->values()->all();
    }

    public function persist(Message $message): Message
    {
        $model = $message->id > 0
            ? MessageModel::findOrFail($message->id)
            : new MessageModel();

        $model->chat_id = $message->chatId;
        $model->external_message_id = $message->externalMessageId;
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
            externalMessageId: $model->external_message_id,
            senderId: $model->sender_id,
            senderType: $model->getSenderTypeEnum(),
            text: $model->text,
            payload: $model->payload ?? [],
            isRead: $model->is_read,
            createdAt: $model->created_at ? \Carbon\Carbon::parse($model->created_at) : null,
        );
    }
}

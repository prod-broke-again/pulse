<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domains\Communication\Entity\Chat;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\ValueObject\ChatStatus;
use App\Infrastructure\Persistence\Eloquent\ChatModel;

final class EloquentChatRepository implements ChatRepositoryInterface
{
    public function findById(int $id): ?Chat
    {
        $model = ChatModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findBySourceAndExternalUser(int $sourceId, string $externalUserId): ?Chat
    {
        $model = ChatModel::where('source_id', $sourceId)
            ->where('external_user_id', $externalUserId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    /** @return list<Chat> */
    public function listByDepartment(int $departmentId, ?ChatStatus $status = null): array
    {
        $query = ChatModel::where('department_id', $departmentId);
        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query->orderBy('id')->get()
            ->map(fn (ChatModel $m) => $this->toEntity($m))
            ->values()
            ->all();
    }

    /** @return list<Chat> */
    public function listAssignedTo(int $userId): array
    {
        return ChatModel::where('assigned_to', $userId)
            ->orderBy('id')
            ->get()
            ->map(fn (ChatModel $m) => $this->toEntity($m))
            ->values()
            ->all();
    }

    public function persist(Chat $chat): Chat
    {
        $model = $chat->id > 0
            ? ChatModel::findOrFail($chat->id)
            : new ChatModel();

        $model->source_id = $chat->sourceId;
        $model->department_id = $chat->departmentId;
        $model->external_user_id = $chat->externalUserId;
        $model->user_metadata = $chat->userMetadata;
        $model->status = $chat->status->value;
        $model->assigned_to = $chat->assignedTo;
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(ChatModel $model): Chat
    {
        return new Chat(
            id: $model->id,
            sourceId: $model->source_id,
            departmentId: $model->department_id,
            externalUserId: $model->external_user_id,
            userMetadata: $model->user_metadata ?? [],
            status: $model->getStatusEnum(),
            assignedTo: $model->assigned_to,
        );
    }
}

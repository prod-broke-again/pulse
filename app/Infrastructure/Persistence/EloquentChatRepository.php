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

    public function findOpenBySourceAndExternalUser(int $sourceId, string $externalUserId): ?Chat
    {
        $model = ChatModel::query()
            ->where('source_id', $sourceId)
            ->where('external_user_id', $externalUserId)
            ->where('status', '!=', ChatStatus::Closed->value)
            ->orderByDesc('id')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findLatestBySourceAndExternalUser(int $sourceId, string $externalUserId): ?Chat
    {
        $model = ChatModel::query()
            ->where('source_id', $sourceId)
            ->where('external_user_id', $externalUserId)
            ->orderByDesc('id')
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
            : new ChatModel;

        $model->source_id = $chat->sourceId;
        $model->external_business_connection_id = $chat->externalBusinessConnectionId;
        $model->department_id = $chat->departmentId;
        $model->external_user_id = $chat->externalUserId;
        $model->user_metadata = $chat->userMetadata;
        $model->status = $chat->status->value;
        $model->assigned_to = $chat->assignedTo;
        $model->topic = $chat->topic;
        $model->ai_suggested_department_id = $chat->aiSuggestedDepartmentId;
        $model->ai_department_confidence = $chat->aiDepartmentConfidence;
        $model->ai_department_assigned_at = $chat->aiDepartmentAssignedAt;
        $model->department_reassigned_by_user_id = $chat->departmentReassignedByUserId;
        $model->last_activity_at = $chat->lastActivityAt;
        $model->previous_chat_id = $chat->previousChatId;
        $model->ai_auto_replies_count = $chat->aiAutoRepliesCount;
        $model->awaiting_client_feedback = $chat->awaitingClientFeedback;
        $model->save();

        return $this->toEntity($model);
    }

    public function touchLastActivityAt(int $chatId): void
    {
        ChatModel::query()->whereKey($chatId)->update(['last_activity_at' => now()]);
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
            topic: $model->topic,
            aiSuggestedDepartmentId: $model->ai_suggested_department_id,
            aiDepartmentConfidence: $model->ai_department_confidence !== null
                ? (float) $model->ai_department_confidence
                : null,
            aiDepartmentAssignedAt: $model->ai_department_assigned_at?->toDateTimeImmutable(),
            departmentReassignedByUserId: $model->department_reassigned_by_user_id,
            externalBusinessConnectionId: $model->external_business_connection_id,
            lastActivityAt: $model->last_activity_at?->toDateTimeImmutable(),
            previousChatId: $model->previous_chat_id,
            aiAutoRepliesCount: (int) $model->ai_auto_replies_count,
            awaitingClientFeedback: (bool) $model->awaiting_client_feedback,
        );
    }
}

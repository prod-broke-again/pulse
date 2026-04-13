<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Domains\Communication\Entity\Chat;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class ChangeChatDepartment
{
    public function __construct(
        private readonly ChatRepositoryInterface $chats,
    ) {}

    public function run(int $chatId, int $departmentId, User $user): Chat
    {
        $domainChat = $this->chats->findById($chatId);
        if ($domainChat === null) {
            throw ValidationException::withMessages([
                'chat' => ['Чат не найден.'],
            ]);
        }

        $department = DepartmentModel::query()
            ->whereKey($departmentId)
            ->where('is_active', true)
            ->first();

        if ($department === null) {
            throw ValidationException::withMessages([
                'department_id' => ['Отдел не найден или неактивен.'],
            ]);
        }

        if ($department->source_id !== $domainChat->sourceId) {
            throw ValidationException::withMessages([
                'department_id' => ['Отдел не относится к источнику этого чата.'],
            ]);
        }

        if (! $user->isAdmin()) {
            $hasDept = $user->departments()->where('departments.id', $departmentId)->exists();
            if (! $hasDept) {
                throw new AuthorizationException('Нет доступа к выбранному отделу.');
            }
        }

        return $this->chats->persist(new Chat(
            id: $domainChat->id,
            sourceId: $domainChat->sourceId,
            departmentId: $departmentId,
            externalUserId: $domainChat->externalUserId,
            userMetadata: $domainChat->userMetadata,
            status: $domainChat->status,
            assignedTo: $domainChat->assignedTo,
            topic: $domainChat->topic,
        ));
    }
}

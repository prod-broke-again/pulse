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
        $domainChat = $this->findChatOrFail($chatId);
        $this->assertDepartmentValidForChat($departmentId, $domainChat);

        if (! $user->isAdmin()) {
            $hasDept = $user->departments()->where('departments.id', $departmentId)->exists();
            if (! $hasDept) {
                throw new AuthorizationException('Нет доступа к выбранному отделу.');
            }
        }

        $departmentReassignedByUserId = $domainChat->departmentReassignedByUserId;
        if ($domainChat->aiSuggestedDepartmentId !== null
            && $departmentId !== $domainChat->aiSuggestedDepartmentId) {
            $departmentReassignedByUserId = $user->id;
        }

        return $this->persistDepartmentChange($domainChat, $departmentId, $departmentReassignedByUserId);
    }

    public function runAsSystem(int $chatId, int $departmentId): Chat
    {
        $domainChat = $this->findChatOrFail($chatId);
        $this->assertDepartmentValidForChat($departmentId, $domainChat);

        return $this->persistDepartmentChange(
            $domainChat,
            $departmentId,
            $domainChat->departmentReassignedByUserId,
        );
    }

    private function findChatOrFail(int $chatId): Chat
    {
        $domainChat = $this->chats->findById($chatId);
        if ($domainChat === null) {
            throw ValidationException::withMessages([
                'chat' => ['Чат не найден.'],
            ]);
        }

        return $domainChat;
    }

    private function assertDepartmentValidForChat(int $departmentId, Chat $domainChat): void
    {
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
    }

    private function persistDepartmentChange(
        Chat $domainChat,
        int $departmentId,
        ?int $departmentReassignedByUserId,
    ): Chat {
        return $this->chats->persist($domainChat->withOverrides([
            'departmentId' => $departmentId,
            'departmentReassignedByUserId' => $departmentReassignedByUserId,
        ]));
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Communication\Repository;

use App\Domains\Communication\Entity\Chat;
use App\Domains\Communication\ValueObject\ChatStatus;

interface ChatRepositoryInterface
{
    public function findById(int $id): ?Chat;

    /** Active conversation (not closed) for source + user. */
    public function findOpenBySourceAndExternalUser(int $sourceId, string $externalUserId): ?Chat;

    /** Latest chat for source + user, any status. */
    public function findLatestBySourceAndExternalUser(int $sourceId, string $externalUserId): ?Chat;

    /** @return list<Chat> */
    public function listByDepartment(int $departmentId, ?ChatStatus $status = null): array;

    /** @return list<Chat> */
    public function listAssignedTo(int $userId): array;

    public function persist(Chat $chat): Chat;

    public function touchLastActivityAt(int $chatId): void;
}

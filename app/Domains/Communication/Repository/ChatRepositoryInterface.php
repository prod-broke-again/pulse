<?php

declare(strict_types=1);

namespace App\Domains\Communication\Repository;

use App\Domains\Communication\Entity\Chat;
use App\Domains\Communication\ValueObject\ChatStatus;

interface ChatRepositoryInterface
{
    public function findById(int $id): ?Chat;

    public function findBySourceAndExternalUser(int $sourceId, string $externalUserId): ?Chat;

    /** @return list<Chat> */
    public function listByDepartment(int $departmentId, ?ChatStatus $status = null): array;

    /** @return list<Chat> */
    public function listAssignedTo(int $userId): array;

    public function persist(Chat $chat): Chat;
}

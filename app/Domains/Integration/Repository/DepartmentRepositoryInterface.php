<?php

declare(strict_types=1);

namespace App\Domains\Integration\Repository;

use App\Domains\Integration\Entity\Department;

interface DepartmentRepositoryInterface
{
    public function findById(int $id): ?Department;

    /** @return list<Department> */
    public function listBySourceId(int $sourceId): array;

    public function persist(Department $department): Department;
}

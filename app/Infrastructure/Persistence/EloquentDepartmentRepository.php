<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domains\Integration\Entity\Department;
use App\Domains\Integration\Repository\DepartmentRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;

final class EloquentDepartmentRepository implements DepartmentRepositoryInterface
{
    public function findById(int $id): ?Department
    {
        $model = DepartmentModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    /** @return list<Department> */
    public function listBySourceId(int $sourceId): array
    {
        return DepartmentModel::where('source_id', $sourceId)
            ->orderBy('id')
            ->get()
            ->map(fn (DepartmentModel $m) => $this->toEntity($m))
            ->values()
            ->all();
    }

    public function persist(Department $department): Department
    {
        $model = $department->id > 0
            ? DepartmentModel::findOrFail($department->id)
            : new DepartmentModel();

        $model->source_id = $department->sourceId;
        $model->name = $department->name;
        $model->slug = $department->slug;
        $model->is_active = $department->isActive;
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(DepartmentModel $model): Department
    {
        return new Department(
            id: $model->id,
            sourceId: $model->source_id,
            name: $model->name,
            slug: $model->slug,
            isActive: $model->is_active,
        );
    }
}

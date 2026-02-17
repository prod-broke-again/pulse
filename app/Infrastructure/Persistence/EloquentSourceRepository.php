<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domains\Integration\Entity\Source;
use App\Domains\Integration\Repository\SourceRepositoryInterface;
use App\Domains\Integration\ValueObject\SourceType;
use App\Infrastructure\Persistence\Eloquent\SourceModel;

final class EloquentSourceRepository implements SourceRepositoryInterface
{
    public function findById(int $id): ?Source
    {
        $model = SourceModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByIdentifier(string $identifier): ?Source
    {
        $model = SourceModel::where('identifier', $identifier)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /** @return list<Source> */
    public function listByType(SourceType $type): array
    {
        return SourceModel::where('type', $type->value)
            ->orderBy('id')
            ->get()
            ->map(fn (SourceModel $m) => $this->toEntity($m))
            ->values()
            ->all();
    }

    public function persist(Source $source): Source
    {
        $model = $source->id > 0
            ? SourceModel::findOrFail($source->id)
            : new SourceModel();

        $model->name = $source->name;
        $model->type = $source->type->value;
        $model->identifier = $source->identifier;
        $model->secret_key = $source->secretKey;
        $model->settings = $source->settings;
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(SourceModel $model): Source
    {
        return new Source(
            id: $model->id,
            name: $model->name,
            type: $model->getTypeEnum(),
            identifier: $model->identifier,
            secretKey: $model->secret_key,
            settings: $model->settings ?? [],
        );
    }
}

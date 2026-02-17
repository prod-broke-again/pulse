<?php

declare(strict_types=1);

namespace App\Domains\Integration\Repository;

use App\Domains\Integration\Entity\Source;
use App\Domains\Integration\ValueObject\SourceType;

interface SourceRepositoryInterface
{
    public function findById(int $id): ?Source;

    public function findByIdentifier(string $identifier): ?Source;

    /** @return list<Source> */
    public function listByType(SourceType $type): array;

    public function persist(Source $source): Source;
}

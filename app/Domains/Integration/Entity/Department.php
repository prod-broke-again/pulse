<?php

declare(strict_types=1);

namespace App\Domains\Integration\Entity;

final readonly class Department
{
    public function __construct(
        public int $id,
        public int $sourceId,
        public string $name,
        public string $slug,
        public bool $isActive,
        public bool $aiEnabled,
        public string $category,
        public ?string $icon = null,
    ) {}
}

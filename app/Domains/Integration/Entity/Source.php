<?php

declare(strict_types=1);

namespace App\Domains\Integration\Entity;

use App\Domains\Integration\ValueObject\SourceType;

final readonly class Source
{
    public function __construct(
        public int $id,
        public string $name,
        public SourceType $type,
        public string $identifier,
        public ?string $secretKey,
        /** @var array<string, mixed> */
        public array $settings,
    ) {}
}

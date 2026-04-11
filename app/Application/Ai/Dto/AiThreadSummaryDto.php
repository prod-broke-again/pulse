<?php

declare(strict_types=1);

namespace App\Application\Ai\Dto;

final readonly class AiThreadSummaryDto
{
    public function __construct(
        public string $summary,
        public ?string $intentTag = null,
    ) {}
}

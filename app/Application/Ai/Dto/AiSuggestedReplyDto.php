<?php

declare(strict_types=1);

namespace App\Application\Ai\Dto;

final readonly class AiSuggestedReplyDto
{
    public function __construct(
        public string $id,
        public string $text,
    ) {}
}

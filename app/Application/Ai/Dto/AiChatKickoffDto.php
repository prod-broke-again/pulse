<?php

declare(strict_types=1);

namespace App\Application\Ai\Dto;

final readonly class AiChatKickoffDto
{
    /**
     * @param  list<AiSuggestedReplyDto>  $replies
     */
    public function __construct(
        public ?string $topic = null,
        public string $summary = '',
        public ?string $intentTag = null,
        public array $replies = [],
    ) {}
}

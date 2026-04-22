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
        public ?int $suggestedDepartmentId = null,
        public ?float $confidence = null,
        /** If set, send this text to the client as an automated first-line support reply. */
        public ?string $autoReplyText = null,
        /** Optional confidence 0-1 for auto-reply; use with configured threshold. */
        public ?float $autoReplyConfidence = null,
        /** If true, skip auto-reply and route to human queue. */
        public bool $escalateToHuman = false,
    ) {}
}

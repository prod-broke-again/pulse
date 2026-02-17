<?php

declare(strict_types=1);

namespace App\Domains\Communication\Event;

final readonly class NewChatMessage
{
    public function __construct(
        public int $chatId,
        public int $messageId,
        public string $text,
    ) {}
}

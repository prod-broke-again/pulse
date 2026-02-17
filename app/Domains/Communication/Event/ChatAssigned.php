<?php

declare(strict_types=1);

namespace App\Domains\Communication\Event;

final readonly class ChatAssigned
{
    public function __construct(
        public int $chatId,
        public int $assignedToUserId,
    ) {}
}

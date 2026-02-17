<?php

declare(strict_types=1);

namespace App\Application\Communication\Dto;

final readonly class MessageDto
{
    public function __construct(
        public int $id,
        public int $chatId,
        public ?int $senderId,
        public string $senderType,
        public string $text,
        /** @var array<string, mixed> */
        public array $payload,
        public bool $isRead,
    ) {}
}

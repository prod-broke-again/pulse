<?php

declare(strict_types=1);

namespace App\Domains\Communication\Entity;

use App\Domains\Communication\ValueObject\SenderType;

final readonly class Message
{
    public function __construct(
        public int $id,
        public int $chatId,
        public ?int $senderId,
        public SenderType $senderType,
        public string $text,
        /** @var array<string, mixed> */
        public array $payload,
        public bool $isRead,
    ) {}
}

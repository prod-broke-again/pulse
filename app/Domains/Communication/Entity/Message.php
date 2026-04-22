<?php

declare(strict_types=1);

namespace App\Domains\Communication\Entity;

use App\Domains\Communication\ValueObject\SenderType;

final readonly class Message
{
    public function __construct(
        public int $id,
        public int $chatId,
        public ?string $externalMessageId,
        public ?int $senderId,
        public SenderType $senderType,
        public string $text,
        /** @var array<string, mixed> */
        public array $payload,
        /** @var list<array{text: string, url?: string, callback_data?: string}>|null */
        public ?array $replyMarkup,
        public bool $isRead,
        public ?\DateTimeInterface $createdAt = null,
        public ?int $replyToId = null,
    ) {}
}

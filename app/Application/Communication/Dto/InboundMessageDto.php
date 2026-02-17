<?php

declare(strict_types=1);

namespace App\Application\Communication\Dto;

final readonly class InboundMessageDto
{
    public function __construct(
        public int $sourceId,
        public int $departmentId,
        public string $externalUserId,
        /** @var array<string, mixed> */
        public array $userMetadata,
        public string $text,
        /** @var array<string, mixed> */
        public array $payload,
    ) {}
}

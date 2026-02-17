<?php

declare(strict_types=1);

namespace App\Application\Communication\Dto;

final readonly class ChatDto
{
    public function __construct(
        public int $id,
        public int $sourceId,
        public int $departmentId,
        public string $externalUserId,
        /** @var array<string, mixed> */
        public array $userMetadata,
        public string $status,
        public ?int $assignedTo,
    ) {}
}

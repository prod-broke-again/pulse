<?php

declare(strict_types=1);

namespace App\Domains\Communication\Entity;

use App\Domains\Communication\ValueObject\ChatStatus;

final readonly class Chat
{
    public function __construct(
        public int $id,
        public int $sourceId,
        public int $departmentId,
        public string $externalUserId,
        /** @var array<string, mixed> */
        public array $userMetadata,
        public ChatStatus $status,
        public ?int $assignedTo,
    ) {}
}

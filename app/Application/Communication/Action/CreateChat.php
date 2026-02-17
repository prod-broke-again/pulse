<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Domains\Communication\Entity\Chat;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\ValueObject\ChatStatus;

final readonly class CreateChat
{
    public function __construct(
        private ChatRepositoryInterface $chatRepository,
    ) {}

    public function run(
        int $sourceId,
        int $departmentId,
        string $externalUserId,
        array $userMetadata = [],
    ): Chat {
        $chat = new Chat(
            id: 0,
            sourceId: $sourceId,
            departmentId: $departmentId,
            externalUserId: $externalUserId,
            userMetadata: $userMetadata,
            status: ChatStatus::New,
            assignedTo: null,
        );

        return $this->chatRepository->persist($chat);
    }
}

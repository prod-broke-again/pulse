<?php

declare(strict_types=1);

namespace App\Application\Communication\Webhook;

use App\Application\Communication\Action\CreateChat;
use App\Domains\Communication\Entity\Chat;
use App\Domains\Communication\Repository\ChatRepositoryInterface;

/**
 * Finds or creates a chat for an inbound webhook and merges user metadata when needed.
 */
final readonly class InboundChatUpsert
{
    public function __construct(
        private ChatRepositoryInterface $chatRepository,
        private CreateChat $createChat,
        private UserMetadataMerger $userMetadataMerger,
    ) {}

    /**
     * @param  array<string, mixed>  $userMetadata
     */
    public function resolve(
        int $sourceId,
        int $departmentId,
        string $externalUserId,
        array $userMetadata,
    ): Chat {
        $chat = $this->chatRepository->findBySourceAndExternalUser($sourceId, $externalUserId);

        if ($chat === null) {
            return $this->createChat->run(
                sourceId: $sourceId,
                departmentId: $departmentId,
                externalUserId: $externalUserId,
                userMetadata: $userMetadata,
            );
        }

        $mergedMetadata = $this->userMetadataMerger->merge($chat->userMetadata, $userMetadata);
        if ($mergedMetadata === $chat->userMetadata) {
            return $chat;
        }

        return $this->chatRepository->persist(new Chat(
            id: $chat->id,
            sourceId: $chat->sourceId,
            departmentId: $chat->departmentId,
            externalUserId: $chat->externalUserId,
            userMetadata: $mergedMetadata,
            status: $chat->status,
            assignedTo: $chat->assignedTo,
            topic: $chat->topic,
        ));
    }
}

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
        ?string $businessConnectionId = null,
    ): Chat {
        $chat = $this->chatRepository->findOpenBySourceAndExternalUser($sourceId, $externalUserId);

        if ($chat === null) {
            $latest = $this->chatRepository->findLatestBySourceAndExternalUser($sourceId, $externalUserId);
            $previousChatId = $latest !== null ? $latest->id : null;

            return $this->createChat->run(
                sourceId: $sourceId,
                departmentId: $departmentId,
                externalUserId: $externalUserId,
                userMetadata: $userMetadata,
                externalBusinessConnectionId: $businessConnectionId,
                previousChatId: $previousChatId,
            );
        }

        $mergedMetadata = $this->userMetadataMerger->merge($chat->userMetadata, $userMetadata);
        $metadataChanged = $mergedMetadata !== $chat->userMetadata;
        $shouldSetBusinessId = $businessConnectionId !== null
            && $businessConnectionId !== ''
            && $chat->externalBusinessConnectionId === null;

        if (! $metadataChanged && ! $shouldSetBusinessId) {
            return $chat;
        }

        $externalBusinessConnectionId = $chat->externalBusinessConnectionId;
        if ($shouldSetBusinessId) {
            $externalBusinessConnectionId = $businessConnectionId;
        }

        return $this->chatRepository->persist($chat->withOverrides([
            'userMetadata' => $metadataChanged ? $mergedMetadata : $chat->userMetadata,
            'externalBusinessConnectionId' => $externalBusinessConnectionId,
        ]));
    }
}

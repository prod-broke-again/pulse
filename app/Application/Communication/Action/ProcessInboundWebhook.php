<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\ValueObject\SenderType;
use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Domains\Integration\Repository\DepartmentRepositoryInterface;
use App\Domains\Integration\Repository\SourceRepositoryInterface;

final readonly class ProcessInboundWebhook
{
    public function __construct(
        private ChatRepositoryInterface $chatRepository,
        private CreateChat $createChat,
        private CreateMessage $createMessage,
        private SourceRepositoryInterface $sourceRepository,
        private DepartmentRepositoryInterface $departmentRepository,
    ) {}

    /** @param array<string, mixed> $payload */
    public function run(
        int $sourceId,
        MessengerProviderInterface $messenger,
        array $payload,
    ): void {
        if (! $messenger->validateWebhook($payload)) {
            throw new \InvalidArgumentException('Invalid webhook payload');
        }

        $source = $this->sourceRepository->findById($sourceId);
        if ($source === null) {
            throw new \InvalidArgumentException("Source not found: {$sourceId}");
        }

        $externalUserId = $this->extractExternalUserId($payload);
        $departmentId = $this->extractDepartmentId($payload, $sourceId);
        $text = $this->extractText($payload);
        $userMetadata = $this->extractUserMetadata($payload);

        $chat = $this->chatRepository->findBySourceAndExternalUser($sourceId, $externalUserId);

        if ($chat === null) {
            $chat = $this->createChat->run(
                sourceId: $sourceId,
                departmentId: $departmentId,
                externalUserId: $externalUserId,
                userMetadata: $userMetadata,
            );
        }

        $this->createMessage->run(
            chatId: $chat->id,
            text: $text,
            senderType: SenderType::Client,
            senderId: null,
            payload: $payload,
        );
    }

    /** @param array<string, mixed> $payload */
    private function extractExternalUserId(array $payload): string
    {
        $id = $payload['user_id']
            ?? $payload['from']['id']
            ?? ($payload['message']['from']['id'] ?? null)
            ?? $payload['external_user_id'] ?? null;
        if ($id === null) {
            throw new \InvalidArgumentException('Payload missing external user identifier');
        }

        return (string) $id;
    }

    /** @param array<string, mixed> $payload */
    private function extractDepartmentId(array $payload, int $sourceId): int
    {
        $id = $payload['department_id'] ?? null;
        if ($id !== null) {
            return (int) $id;
        }
        $departments = $this->departmentRepository->listBySourceId($sourceId);
        $first = $departments[0] ?? null;
        if ($first === null) {
            throw new \InvalidArgumentException("No department configured for source: {$sourceId}");
        }
        return $first->id;
    }

    /** @param array<string, mixed> $payload */
    private function extractText(array $payload): string
    {
        $text = $payload['text'] ?? $payload['message']['text'] ?? $payload['body'] ?? '';
        return (string) $text;
    }

    /** @param array<string, mixed> $payload */
    private function extractUserMetadata(array $payload): array
    {
        $from = $payload['from'] ?? $payload['message']['from'] ?? $payload['user'] ?? [];
        if (! is_array($from)) {
            return [];
        }

        return $from;
    }
}

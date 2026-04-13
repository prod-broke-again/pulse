<?php

declare(strict_types=1);

namespace App\Application\Communication\Webhook;

use App\Domains\Integration\Repository\DepartmentRepositoryInterface;

/**
 * Extracts scalar fields from heterogeneous messenger webhook payloads.
 */
final readonly class WebhookPayloadExtractor
{
    public function __construct(
        private DepartmentRepositoryInterface $departmentRepository,
        private UserMetadataMerger $userMetadataMerger,
        private InboundAttachmentExtractor $inboundAttachmentExtractor,
    ) {}

    /** @param array<string, mixed> $payload */
    public function extractExternalUserId(array $payload): string
    {
        $id = $payload['user_id']
            ?? $payload['from']['id']
            ?? ($payload['message']['from']['id'] ?? null)
            ?? ($payload['object']['message']['from_id'] ?? null)
            ?? ($payload['object']['from_id'] ?? null)
            ?? $payload['external_user_id'] ?? null;
        if ($id === null) {
            throw new \InvalidArgumentException('Payload missing external user identifier');
        }

        return (string) $id;
    }

    /** @param array<string, mixed> $payload */
    public function extractDepartmentId(array $payload, int $sourceId): int
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

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array{url: string, file_name: string, mime_type: string, kind?: string}>  $attachments
     */
    public function extractText(array $payload, array $attachments): string
    {
        $text = $payload['text']
            ?? $payload['message']['text']
            ?? ($payload['object']['message']['text'] ?? null)
            ?? ($payload['object']['text'] ?? null)
            ?? $payload['body']
            ?? '';
        $normalized = trim((string) $text);
        if ($normalized !== '') {
            return $normalized;
        }

        return $this->inboundAttachmentExtractor->buildAttachmentPlaceholderText($attachments);
    }

    /** @param array<string, mixed> $payload */
    public function extractUserMetadata(array $payload): array
    {
        $from = $payload['from'] ?? $payload['message']['from'] ?? $payload['user'] ?? null;

        if (is_array($from)) {
            $metadata = $from;
            $name = $this->extractDisplayName($from);
            if ($name !== null) {
                $metadata['name'] = $name;
            }

            return $metadata;
        }

        $vkFromId = $payload['object']['message']['from_id'] ?? $payload['object']['from_id'] ?? null;
        if ($vkFromId !== null) {
            return ['id' => $vkFromId];
        }

        return [];
    }

    /** @param array<string, mixed> $payload */
    public function extractExternalMessageId(array $payload): ?string
    {
        $id = $payload['message']['message_id']
            ?? ($payload['object']['message']['id'] ?? null)
            ?? $payload['object']['message_id']
            ?? $payload['message_id']
            ?? $payload['update_id'] ?? null;
        if ($id === null) {
            return null;
        }

        return (string) $id;
    }

    /** @param array<string, mixed> $from */
    private function extractDisplayName(array $from): ?string
    {
        $name = $from['name'] ?? null;
        if (is_scalar($name)) {
            $normalized = trim((string) $name);
            if ($normalized !== '' && ! $this->userMetadataMerger->isPlaceholderGuestName($normalized)) {
                return $normalized;
            }
        }

        $firstName = $from['first_name'] ?? null;
        $lastName = $from['last_name'] ?? null;
        $first = is_scalar($firstName) ? trim((string) $firstName) : '';
        $last = is_scalar($lastName) ? trim((string) $lastName) : '';
        $combined = trim($first.' '.$last);
        if ($combined !== '') {
            return $combined;
        }

        $username = $from['username'] ?? null;
        if (is_scalar($username)) {
            $normalized = trim((string) $username);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }
}

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
            ?? ($payload['business_message']['from']['id'] ?? null)
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
        $message = $payload['message'] ?? $payload['edited_message'] ?? $payload['channel_post'] ?? $payload['business_message'] ?? null;
        $text = $payload['text']
            ?? (is_array($message) ? ($message['text'] ?? $message['caption'] ?? null) : null)
            ?? ($payload['object']['message']['text'] ?? null)
            ?? ($payload['object']['message']['caption'] ?? null)
            ?? ($payload['object']['text'] ?? null)
            ?? $payload['body']
            ?? '';
        $normalized = trim((string) $text);
        if ($normalized !== '') {
            return $normalized;
        }

        return $this->inboundAttachmentExtractor->buildAttachmentPlaceholderText($attachments);
    }

    /**
     * External id of the message this update replies to (Telegram/VK-style), if present.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractReplyToExternalMessageId(array $payload): ?string
    {
        $containers = [
            $payload['message'] ?? null,
            $payload['edited_message'] ?? null,
            $payload['channel_post'] ?? null,
            $payload['business_message'] ?? null,
        ];
        if (isset($payload['callback_query']['message']) && is_array($payload['callback_query']['message'])) {
            $containers[] = $payload['callback_query']['message'];
        }
        foreach ($containers as $msg) {
            if (! is_array($msg)) {
                continue;
            }
            $reply = $msg['reply_to_message'] ?? null;
            if (is_array($reply) && isset($reply['message_id'])) {
                return (string) $reply['message_id'];
            }
        }

        $vkReply = $payload['object']['message']['reply_message'] ?? null;
        if (is_array($vkReply) && isset($vkReply['id'])) {
            return (string) $vkReply['id'];
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    public function extractUserMetadata(array $payload): array
    {
        $from = $payload['from'] ?? $payload['message']['from'] ?? $payload['business_message']['from'] ?? $payload['user'] ?? null;

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

    /**
     * Telegram album id when the user sends several photos as one group.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractTelegramMediaGroupId(array $payload): ?string
    {
        $containers = [
            $payload['message'] ?? null,
            $payload['edited_message'] ?? null,
            $payload['channel_post'] ?? null,
            $payload['business_message'] ?? null,
        ];
        foreach ($containers as $msg) {
            if (! is_array($msg)) {
                continue;
            }
            $id = $msg['media_group_id'] ?? null;
            if ($id === null) {
                continue;
            }
            if (is_int($id) || (is_string($id) && $id !== '')) {
                return (string) $id;
            }
        }

        return null;
    }

    /**
     * Raw user-visible text from a Telegram message (caption or text), without attachment placeholder fallback.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractRawTelegramMessageText(array $payload): string
    {
        $message = $payload['message'] ?? $payload['edited_message'] ?? $payload['channel_post'] ?? $payload['business_message'] ?? null;
        if (! is_array($message)) {
            return '';
        }
        $text = $message['caption'] ?? $message['text'] ?? null;

        return trim((string) $text);
    }

    /** @param array<string, mixed> $payload */
    public function extractBusinessConnectionId(array $payload): ?string
    {
        $bm = $payload['business_message'] ?? null;
        if (! is_array($bm)) {
            return null;
        }
        $id = $bm['business_connection_id'] ?? null;
        if (! is_string($id) || $id === '') {
            return null;
        }

        return $id;
    }

    /** @param array<string, mixed> $payload */
    public function extractExternalMessageId(array $payload): ?string
    {
        $id = (is_array($payload['message'] ?? null) ? ($payload['message']['message_id'] ?? null) : null)
            ?? (is_array($payload['business_message'] ?? null) ? ($payload['business_message']['message_id'] ?? null) : null)
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

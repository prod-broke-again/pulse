<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\ValueObject\SenderType;
use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Domains\Integration\Repository\DepartmentRepositoryInterface;
use App\Domains\Integration\Repository\SourceRepositoryInterface;
use App\Jobs\DownloadInboundAttachmentJob;

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
        $externalMessageId = $this->extractExternalMessageId($payload);

        $chat = $this->chatRepository->findBySourceAndExternalUser($sourceId, $externalUserId);

        if ($chat === null) {
            $chat = $this->createChat->run(
                sourceId: $sourceId,
                departmentId: $departmentId,
                externalUserId: $externalUserId,
                userMetadata: $userMetadata,
            );
        }

        $message = $this->createMessage->run(
            chatId: $chat->id,
            text: $text,
            senderType: SenderType::Client,
            senderId: null,
            payload: $payload,
            externalMessageId: $externalMessageId,
        );

        $this->dispatchAttachmentDownloads($payload, $message->id);
    }

    /** @param array<string, mixed> $payload */
    private function dispatchAttachmentDownloads(array $payload, int $messageId): void
    {
        $attachments = $this->extractAttachments($payload);
        foreach ($attachments as $attachment) {
            DownloadInboundAttachmentJob::dispatch(
                $messageId,
                $attachment['url'],
                $attachment['file_name'],
                $attachment['mime_type'],
            );
        }
    }

    /**
     * Extract downloadable attachment info from webhook payload.
     *
     * @param array<string, mixed> $payload
     * @return list<array{url: string, file_name: string, mime_type: string}>
     */
    private function extractAttachments(array $payload): array
    {
        $attachments = [];
        $message = $payload['message'] ?? $payload;

        // Telegram photo
        if (isset($message['photo']) && is_array($message['photo'])) {
            $photo = end($message['photo']);
            if (isset($photo['file_url'])) {
                $attachments[] = [
                    'url' => $photo['file_url'],
                    'file_name' => ($photo['file_unique_id'] ?? 'photo').'.jpg',
                    'mime_type' => 'image/jpeg',
                ];
            }
        }

        // Telegram document
        if (isset($message['document']['file_url'])) {
            $doc = $message['document'];
            $attachments[] = [
                'url' => $doc['file_url'],
                'file_name' => $doc['file_name'] ?? 'document',
                'mime_type' => $doc['mime_type'] ?? 'application/octet-stream',
            ];
        }

        // Telegram audio/voice
        foreach (['audio', 'voice'] as $type) {
            if (isset($message[$type]['file_url'])) {
                $item = $message[$type];
                $attachments[] = [
                    'url' => $item['file_url'],
                    'file_name' => $item['file_name'] ?? $type.'.ogg',
                    'mime_type' => $item['mime_type'] ?? 'audio/ogg',
                ];
            }
        }

        // VK attachments
        if (isset($payload['object']['attachments']) && is_array($payload['object']['attachments'])) {
            foreach ($payload['object']['attachments'] as $vkAttachment) {
                $type = $vkAttachment['type'] ?? null;
                if ($type === 'photo' && isset($vkAttachment['photo']['sizes'])) {
                    $sizes = $vkAttachment['photo']['sizes'];
                    $best = end($sizes);
                    if (isset($best['url'])) {
                        $attachments[] = [
                            'url' => $best['url'],
                            'file_name' => 'photo.jpg',
                            'mime_type' => 'image/jpeg',
                        ];
                    }
                }
                if ($type === 'doc' && isset($vkAttachment['doc']['url'])) {
                    $attachments[] = [
                        'url' => $vkAttachment['doc']['url'],
                        'file_name' => $vkAttachment['doc']['title'] ?? 'document',
                        'mime_type' => 'application/octet-stream',
                    ];
                }
            }
        }

        return $attachments;
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

    /** @param array<string, mixed> $payload */
    private function extractExternalMessageId(array $payload): ?string
    {
        $id = $payload['message']['message_id']
            ?? $payload['object']['message_id']
            ?? $payload['message_id']
            ?? $payload['update_id'] ?? null;
        if ($id === null) {
            return null;
        }

        return (string) $id;
    }
}

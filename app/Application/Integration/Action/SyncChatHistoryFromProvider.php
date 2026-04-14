<?php

declare(strict_types=1);

namespace App\Application\Integration\Action;

use App\Application\Communication\Action\CreateMessage;
use App\Application\Communication\Webhook\InboundAttachmentExtractor;
use App\Domains\Communication\Entity\Chat as ChatEntity;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\Repository\MessageRepositoryInterface;
use App\Domains\Communication\ValueObject\SenderType;
use App\Domains\Integration\ValueObject\SourceType;
use App\Infrastructure\Integration\Client\TelegramApiClient;
use App\Infrastructure\Integration\Client\VkApiClient;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Jobs\DownloadInboundAttachmentJob;
use Illuminate\Support\Facades\Log;

/**
 * Best-effort sync: VK imports missing history; Telegram refreshes guest metadata (Bot API has no message history).
 */
final readonly class SyncChatHistoryFromProvider
{
    public function __construct(
        private ChatRepositoryInterface $chatRepository,
        private MessageRepositoryInterface $messageRepository,
        private CreateMessage $createMessage,
        private InboundAttachmentExtractor $inboundAttachmentExtractor,
    ) {}

    /**
     * @return array{imported: int, refreshed_metadata?: bool, note?: string}
     */
    public function run(ChatModel $chat): array
    {
        $source = SourceModel::query()->find($chat->source_id);
        if ($source === null) {
            return ['imported' => 0, 'note' => 'source_not_found'];
        }

        try {
            $type = SourceType::from($source->type);
        } catch (\ValueError) {
            return ['imported' => 0, 'note' => 'unknown_source'];
        }

        return match ($type) {
            SourceType::Vk => $this->syncVk($chat, $source),
            SourceType::Tg => $this->syncTelegramMetadata($chat, $source),
            SourceType::Max => ['imported' => 0, 'note' => 'max_history_sync_not_available'],
            SourceType::Web => ['imported' => 0, 'note' => 'web_has_no_external_history'],
        };
    }

    /**
     * @return array{imported: int, refreshed_metadata?: bool, note?: string}
     */
    private function syncTelegramMetadata(ChatModel $chat, SourceModel $source): array
    {
        $token = isset($source->settings['bot_token']) && is_string($source->settings['bot_token'])
            ? trim($source->settings['bot_token'])
            : '';
        if ($token === '') {
            return ['imported' => 0, 'note' => 'telegram_token_missing'];
        }

        $client = new TelegramApiClient($token);
        $userId = (int) $chat->external_user_id;
        if ($userId <= 0) {
            return ['imported' => 0, 'note' => 'invalid_external_user'];
        }

        $avatarUrl = $client->getUserProfilePhotoUrl($userId);
        $meta = is_array($chat->user_metadata) ? $chat->user_metadata : [];
        if ($avatarUrl !== null && $avatarUrl !== '') {
            $meta['avatar_url'] = $avatarUrl;
        }

        $entity = $this->chatRepository->findById($chat->id);
        if ($entity === null) {
            return ['imported' => 0, 'note' => 'chat_not_found'];
        }

        $updated = new ChatEntity(
            id: $entity->id,
            sourceId: $entity->sourceId,
            departmentId: $entity->departmentId,
            externalUserId: $entity->externalUserId,
            userMetadata: array_merge($entity->userMetadata, $meta),
            status: $entity->status,
            assignedTo: $entity->assignedTo,
            topic: $entity->topic,
        );
        $this->chatRepository->persist($updated);

        return ['imported' => 0, 'refreshed_metadata' => true, 'note' => 'telegram_bot_api_has_no_message_history'];
    }

    /**
     * @return array{imported: int, refreshed_metadata?: bool, note?: string}
     */
    private function syncVk(ChatModel $chat, SourceModel $source): array
    {
        $token = isset($source->settings['access_token']) && is_string($source->settings['access_token'])
            ? trim($source->settings['access_token'])
            : '';
        if ($token === '') {
            $token = (string) config('pulse.vk.bot_token', '');
        }
        if ($token === '') {
            return ['imported' => 0, 'note' => 'vk_token_missing'];
        }

        $vk = new VkApiClient($token);
        $peerId = (int) $chat->external_user_id;

        try {
            $history = $vk->getHistory($peerId, 200, 0);
        } catch (\Throwable $e) {
            Log::warning('VK getHistory failed', ['chat_id' => $chat->id, 'error' => $e->getMessage()]);

            return ['imported' => 0, 'note' => 'vk_history_failed'];
        }

        $items = $history['items'] ?? [];
        if (! is_array($items)) {
            return ['imported' => 0, 'note' => 'vk_empty_response'];
        }

        $userPeer = (int) $chat->external_user_id;
        $imported = 0;

        foreach ($items as $msg) {
            if (! is_array($msg) || ! isset($msg['id'])) {
                continue;
            }
            $externalId = (string) $msg['id'];

            if ($this->messageRepository->findByChatAndExternalMessageId($chat->id, $externalId) !== null) {
                continue;
            }

            $text = isset($msg['text']) && is_string($msg['text']) ? trim($msg['text']) : '';
            $fromId = isset($msg['from_id']) ? (int) $msg['from_id'] : 0;

            $senderType = $fromId === $userPeer ? SenderType::Client : SenderType::Moderator;

            $syntheticPayload = [
                'object' => [
                    'message' => $msg,
                ],
            ];
            $attachments = $this->inboundAttachmentExtractor->extract($syntheticPayload);
            $normalized = $this->normalizeVkAttachmentsForExtract($attachments);
            if ($text === '' && $normalized === []) {
                continue;
            }
            if ($text === '') {
                $text = $this->inboundAttachmentExtractor->buildAttachmentPlaceholderText(
                    array_map(static fn (array $a): array => [
                        'url' => $a['url'],
                        'file_name' => $a['file_name'],
                        'mime_type' => $a['mime_type'],
                        'kind' => $a['kind'] ?? null,
                    ], $normalized),
                );
            }

            $created = $this->createMessage->run(
                chatId: $chat->id,
                text: $text,
                senderType: $senderType,
                senderId: null,
                payload: $syntheticPayload,
                externalMessageId: $externalId,
            );

            $imported++;

            foreach ($normalized as $att) {
                DownloadInboundAttachmentJob::dispatch(
                    $created->id,
                    $att['url'],
                    $att['file_name'],
                    $att['mime_type'],
                    $att['kind'] ?? null,
                );
            }
        }

        return ['imported' => $imported];
    }

    /**
     * @param  list<array{url?: string, telegram_file_id?: string, file_name: string, mime_type: string, kind?: string}>  $attachments
     * @return list<array{url: string, file_name: string, mime_type: string, kind?: string|null}>
     */
    private function normalizeVkAttachmentsForExtract(array $attachments): array
    {
        $out = [];
        foreach ($attachments as $a) {
            $url = isset($a['url']) && is_string($a['url']) ? trim($a['url']) : '';
            if ($url === '') {
                continue;
            }
            $out[] = [
                'url' => $url,
                'file_name' => $a['file_name'],
                'mime_type' => is_string($a['mime_type'] ?? null) ? $a['mime_type'] : 'application/octet-stream',
                'kind' => isset($a['kind']) && is_string($a['kind']) ? $a['kind'] : null,
            ];
        }

        return $out;
    }
}

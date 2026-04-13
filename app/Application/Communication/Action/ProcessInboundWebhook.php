<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Domains\Communication\Entity\Chat;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\ValueObject\SenderType;
use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Domains\Integration\Repository\DepartmentRepositoryInterface;
use App\Domains\Integration\Repository\SourceRepositoryInterface;
use App\Domains\Integration\ValueObject\SourceType;
use App\Infrastructure\Integration\Client\VkApiClient;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Jobs\DownloadInboundAttachmentJob;
use App\Services\MaybeSendOfflineAutoReply;
use Illuminate\Support\Facades\Log;

final readonly class ProcessInboundWebhook
{
    public function __construct(
        private ChatRepositoryInterface $chatRepository,
        private CreateChat $createChat,
        private CreateMessage $createMessage,
        private SourceRepositoryInterface $sourceRepository,
        private DepartmentRepositoryInterface $departmentRepository,
        private MaybeSendOfflineAutoReply $maybeSendOfflineAutoReply,
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
        $attachments = $this->extractAttachments($payload);
        $text = $this->extractText($payload, $attachments);
        $userMetadata = $this->extractUserMetadata($payload);
        $userMetadata = $this->enrichUserMetadataFromSource($source->type, $source->settings, $payload, $userMetadata);
        $externalMessageId = $this->extractExternalMessageId($payload);

        $chat = $this->chatRepository->findBySourceAndExternalUser($sourceId, $externalUserId);

        if ($chat === null) {
            $chat = $this->createChat->run(
                sourceId: $sourceId,
                departmentId: $departmentId,
                externalUserId: $externalUserId,
                userMetadata: $userMetadata,
            );
        } else {
            $mergedMetadata = $this->mergeUserMetadata($chat->userMetadata, $userMetadata);
            if ($mergedMetadata !== $chat->userMetadata) {
                $chat = $this->chatRepository->persist(new Chat(
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

        $message = $this->createMessage->run(
            chatId: $chat->id,
            text: $text,
            senderType: SenderType::Client,
            senderId: null,
            payload: $payload,
            externalMessageId: $externalMessageId,
        );

        $this->dispatchAttachmentDownloads($attachments, $message->id);

        $chatModel = ChatModel::query()->find($chat->id);
        if ($chatModel !== null) {
            $this->maybeSendOfflineAutoReply->run($chatModel);
        }
    }

    /**
     * @param  list<array{url: string, file_name: string, mime_type: string, kind?: string}>  $attachments
     */
    private function dispatchAttachmentDownloads(array $attachments, int $messageId): void
    {
        foreach ($attachments as $attachment) {
            DownloadInboundAttachmentJob::dispatch(
                $messageId,
                $attachment['url'],
                $attachment['file_name'],
                $attachment['mime_type'],
                $attachment['kind'] ?? null,
            );
        }
    }

    /**
     * Extract downloadable attachment info from webhook payload.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{url: string, file_name: string, mime_type: string, kind?: string}>
     */
    private function extractAttachments(array $payload): array
    {
        $attachments = [];
        $message = $payload['message'] ?? $payload;
        $vkMessage = $payload['object']['message'] ?? null;

        // Telegram photo
        if (isset($message['photo']) && is_array($message['photo'])) {
            $photo = end($message['photo']);
            if (isset($photo['file_url'])) {
                $attachments[] = [
                    'url' => $photo['file_url'],
                    'file_name' => ($photo['file_unique_id'] ?? 'photo').'.jpg',
                    'mime_type' => 'image/jpeg',
                    'kind' => 'photo',
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
                'kind' => 'document',
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
                    'kind' => $type === 'voice' ? 'voice' : 'audio',
                ];
            }
        }

        // VK attachments
        $vkAttachments = null;
        if (is_array($vkMessage) && isset($vkMessage['attachments']) && is_array($vkMessage['attachments'])) {
            $vkAttachments = $vkMessage['attachments'];
        } elseif (isset($payload['object']['attachments']) && is_array($payload['object']['attachments'])) {
            // Backward-compatible fallback for legacy VK callback payloads.
            $vkAttachments = $payload['object']['attachments'];
        }

        if (is_array($vkAttachments)) {
            foreach ($vkAttachments as $vkAttachment) {
                $type = $vkAttachment['type'] ?? null;
                if ($type === 'photo' && isset($vkAttachment['photo']['sizes'])) {
                    $sizes = $vkAttachment['photo']['sizes'];
                    $best = end($sizes);
                    if (isset($best['url'])) {
                        $attachments[] = [
                            'url' => $best['url'],
                            'file_name' => 'photo.jpg',
                            'mime_type' => 'image/jpeg',
                            'kind' => 'photo',
                        ];
                    }
                }
                if ($type === 'doc' && isset($vkAttachment['doc']['url'])) {
                    $attachments[] = [
                        'url' => $vkAttachment['doc']['url'],
                        'file_name' => $vkAttachment['doc']['title'] ?? 'document',
                        'mime_type' => $vkAttachment['doc']['ext'] ?? 'application/octet-stream',
                        'kind' => 'document',
                    ];
                }
                if ($type === 'audio_message' && isset($vkAttachment['audio_message'])) {
                    $audio = $vkAttachment['audio_message'];
                    $url = $audio['link_ogg'] ?? $audio['link_mp3'] ?? null;
                    if (is_string($url) && $url !== '') {
                        $mimeType = isset($audio['link_ogg']) ? 'audio/ogg' : 'audio/mpeg';
                        $attachments[] = [
                            'url' => $url,
                            'file_name' => isset($audio['id']) ? ('voice_'.$audio['id'].'.ogg') : 'voice.ogg',
                            'mime_type' => $mimeType,
                            'kind' => 'voice',
                        ];
                    }
                }
                if ($type === 'sticker' && isset($vkAttachment['sticker'])) {
                    $sticker = $vkAttachment['sticker'];
                    $images = $sticker['images_with_background'] ?? $sticker['images'] ?? null;
                    if (is_array($images) && $images !== []) {
                        $best = end($images);
                        if (is_array($best) && isset($best['url']) && is_string($best['url'])) {
                            $attachments[] = [
                                'url' => $best['url'],
                                'file_name' => isset($sticker['sticker_id']) ? ('sticker_'.$sticker['sticker_id'].'.webp') : 'sticker.webp',
                                'mime_type' => 'image/webp',
                                'kind' => 'sticker',
                            ];
                        }
                    }
                }
            }
        }

        return $this->uniqueAttachments($attachments);
    }

    /** @param array<string, mixed> $payload */
    private function extractExternalUserId(array $payload): string
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
    /**
     * @param  list<array{url: string, file_name: string, mime_type: string, kind?: string}>  $attachments
     */
    private function extractText(array $payload, array $attachments): string
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

        return $this->buildAttachmentPlaceholderText($attachments);
    }

    /** @param array<string, mixed> $payload */
    private function extractUserMetadata(array $payload): array
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
    private function extractExternalMessageId(array $payload): ?string
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
            if ($normalized !== '' && ! $this->isPlaceholderGuestName($normalized)) {
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

    private function isPlaceholderGuestName(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['гость', 'guest', 'клиент', 'client'], true);
    }

    /**
     * @param  list<array{url: string, file_name: string, mime_type: string, kind?: string}>  $attachments
     */
    private function buildAttachmentPlaceholderText(array $attachments): string
    {
        if ($attachments === []) {
            return '[Сообщение без текста]';
        }

        $kinds = array_values(array_filter(array_map(
            static fn (array $item): string => (string) ($item['kind'] ?? ''),
            $attachments
        )));
        $firstKind = $kinds[0] ?? '';
        $count = count($attachments);

        if ($count > 1) {
            return '[Вложений: '.$count.']';
        }

        return match ($firstKind) {
            'sticker' => '[Стикер]',
            'voice' => '[Голосовое сообщение]',
            'photo' => '[Фото]',
            'audio' => '[Аудио]',
            'document' => '[Документ]',
            default => '[Вложение]',
        };
    }

    /**
     * @param  list<array{url: string, file_name: string, mime_type: string, kind?: string}>  $attachments
     * @return list<array{url: string, file_name: string, mime_type: string, kind?: string}>
     */
    private function uniqueAttachments(array $attachments): array
    {
        $unique = [];
        $seen = [];

        foreach ($attachments as $attachment) {
            $url = trim((string) ($attachment['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $signature = mb_strtolower($url.'|'.($attachment['kind'] ?? '').'|'.($attachment['file_name'] ?? ''));
            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $unique[] = $attachment;
        }

        return $unique;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $userMetadata
     * @return array<string, mixed>
     */
    private function enrichUserMetadataFromSource(
        SourceType $sourceType,
        array $settings,
        array $payload,
        array $userMetadata,
    ): array {
        if ($sourceType !== SourceType::Vk) {
            return $userMetadata;
        }

        $currentName = isset($userMetadata['name']) && is_scalar($userMetadata['name'])
            ? trim((string) $userMetadata['name'])
            : '';
        if ($currentName !== '' && ! $this->isPlaceholderGuestName($currentName)) {
            return $userMetadata;
        }

        $fromId = $payload['object']['message']['from_id'] ?? $payload['object']['from_id'] ?? null;
        if (! is_numeric($fromId)) {
            return $userMetadata;
        }

        $fromId = (int) $fromId;
        if ($fromId <= 0) {
            return $userMetadata;
        }

        $token = isset($settings['access_token']) && is_string($settings['access_token'])
            ? trim($settings['access_token'])
            : '';
        if ($token === '') {
            $token = trim((string) config('pulse.vk.bot_token', ''));
        }
        if ($token === '') {
            return $userMetadata;
        }

        try {
            $profile = (new VkApiClient($token))->getUserProfile($fromId);
            if (! is_array($profile) || $profile === []) {
                return $userMetadata;
            }

            return $this->mergeUserMetadata($userMetadata, $profile);
        } catch (\Throwable $e) {
            Log::warning('VK users.get failed while enriching webhook metadata', [
                'from_id' => $fromId,
                'error' => $e->getMessage(),
            ]);

            return $userMetadata;
        }
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeUserMetadata(array $current, array $incoming): array
    {
        $merged = $current;

        foreach ($incoming as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    continue;
                }
            }

            if ($key === 'name' && is_string($value) && $this->isPlaceholderGuestName($value)) {
                continue;
            }

            $existing = $merged[$key] ?? null;
            $shouldReplaceName = $key === 'name'
                && is_string($value)
                && (! is_string($existing) || $existing === '' || $this->isPlaceholderGuestName($existing));

            if ($shouldReplaceName || $existing === null || $existing === '' || $existing === []) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}

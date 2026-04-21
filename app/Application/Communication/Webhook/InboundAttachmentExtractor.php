<?php

declare(strict_types=1);

namespace App\Application\Communication\Webhook;

/**
 * Extracts downloadable attachment descriptors from messenger webhook payloads.
 *
 * Telegram and VK shapes are handled via dedicated private methods for easier extension.
 */
final class InboundAttachmentExtractor
{
    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{url?: string, telegram_file_id?: string, file_name: string, mime_type: string, kind?: string}>
     */
    public function extract(array $payload): array
    {
        $attachments = [];
        $message = $payload['message'] ?? $payload['edited_message'] ?? $payload['channel_post'] ?? $payload['business_message'] ?? $payload;

        $attachments = array_merge($attachments, $this->extractTelegramAttachments($message));
        $attachments = array_merge($attachments, $this->extractVkAttachments($payload));

        return $this->uniqueAttachments($attachments);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return list<array{url?: string, telegram_file_id?: string, file_name: string, mime_type: string, kind?: string}>
     */
    private function extractTelegramAttachments(array $message): array
    {
        $attachments = [];

        if (isset($message['photo']) && is_array($message['photo'])) {
            $photo = end($message['photo']);
            if (is_array($photo)) {
                if (isset($photo['file_url']) && is_string($photo['file_url']) && $photo['file_url'] !== '') {
                    $attachments[] = [
                        'url' => $photo['file_url'],
                        'file_name' => ($photo['file_unique_id'] ?? 'photo').'.jpg',
                        'mime_type' => 'image/jpeg',
                        'kind' => 'photo',
                    ];
                } elseif (isset($photo['file_id']) && is_string($photo['file_id']) && $photo['file_id'] !== '') {
                    $attachments[] = [
                        'telegram_file_id' => $photo['file_id'],
                        'file_name' => ($photo['file_unique_id'] ?? 'photo').'.jpg',
                        'mime_type' => 'image/jpeg',
                        'kind' => 'photo',
                    ];
                }
            }
        }

        if (isset($message['document']) && is_array($message['document'])) {
            $doc = $message['document'];
            if (isset($doc['file_url']) && is_string($doc['file_url']) && $doc['file_url'] !== '') {
                $attachments[] = [
                    'url' => $doc['file_url'],
                    'file_name' => $doc['file_name'] ?? 'document',
                    'mime_type' => $doc['mime_type'] ?? 'application/octet-stream',
                    'kind' => 'document',
                ];
            } elseif (isset($doc['file_id']) && is_string($doc['file_id']) && $doc['file_id'] !== '') {
                $attachments[] = [
                    'telegram_file_id' => $doc['file_id'],
                    'file_name' => $doc['file_name'] ?? 'document',
                    'mime_type' => $doc['mime_type'] ?? 'application/octet-stream',
                    'kind' => 'document',
                ];
            }
        }

        foreach (['audio', 'voice'] as $type) {
            if (! isset($message[$type]) || ! is_array($message[$type])) {
                continue;
            }
            $item = $message[$type];
            if (isset($item['file_url']) && is_string($item['file_url']) && $item['file_url'] !== '') {
                $attachments[] = [
                    'url' => $item['file_url'],
                    'file_name' => $item['file_name'] ?? $type.'.ogg',
                    'mime_type' => $item['mime_type'] ?? 'audio/ogg',
                    'kind' => $type === 'voice' ? 'voice' : 'audio',
                ];
            } elseif (isset($item['file_id']) && is_string($item['file_id']) && $item['file_id'] !== '') {
                $attachments[] = [
                    'telegram_file_id' => $item['file_id'],
                    'file_name' => $item['file_name'] ?? $type.'.ogg',
                    'mime_type' => $item['mime_type'] ?? 'audio/ogg',
                    'kind' => $type === 'voice' ? 'voice' : 'audio',
                ];
            }
        }

        if (isset($message['sticker']) && is_array($message['sticker'])) {
            $sticker = $message['sticker'];
            $unique = isset($sticker['file_unique_id']) && is_string($sticker['file_unique_id'])
                ? $sticker['file_unique_id']
                : 'sticker';
            $isVideo = ($sticker['is_video'] ?? false) === true;
            $isAnimated = ($sticker['is_animated'] ?? false) === true;
            if ($isVideo) {
                $ext = '.webm';
                $mime = 'video/webm';
            } elseif ($isAnimated) {
                $ext = '.tgs';
                $mime = 'application/x-tgs';
            } else {
                $ext = '.webp';
                $mime = 'image/webp';
            }
            $fileName = 'sticker_'.$unique.$ext;
            if (isset($sticker['file_url']) && is_string($sticker['file_url']) && $sticker['file_url'] !== '') {
                $attachments[] = [
                    'url' => $sticker['file_url'],
                    'file_name' => $fileName,
                    'mime_type' => $mime,
                    'kind' => 'sticker',
                ];
            } elseif (isset($sticker['file_id']) && is_string($sticker['file_id']) && $sticker['file_id'] !== '') {
                $attachments[] = [
                    'telegram_file_id' => $sticker['file_id'],
                    'file_name' => $fileName,
                    'mime_type' => $mime,
                    'kind' => 'sticker',
                ];
            }
        }

        foreach (['video', 'video_note', 'animation'] as $key) {
            if (! isset($message[$key]) || ! is_array($message[$key])) {
                continue;
            }
            $item = $message[$key];
            $kind = $key === 'animation' ? 'animation' : $key;
            $defaultName = match ($key) {
                'video' => 'video',
                'video_note' => 'video_note',
                default => 'animation',
            };
            $defaultMime = match ($key) {
                'animation' => 'video/mp4',
                'video_note' => 'video/mp4',
                default => 'video/mp4',
            };
            $uniqueId = isset($item['file_unique_id']) && is_string($item['file_unique_id'])
                ? $item['file_unique_id']
                : $defaultName;
            $fileName = $item['file_name'] ?? ($defaultName.'_'.$uniqueId.'.mp4');
            $mimeType = isset($item['mime_type']) && is_string($item['mime_type']) && $item['mime_type'] !== ''
                ? $item['mime_type']
                : $defaultMime;
            if (isset($item['file_url']) && is_string($item['file_url']) && $item['file_url'] !== '') {
                $attachments[] = [
                    'url' => $item['file_url'],
                    'file_name' => $fileName,
                    'mime_type' => $mimeType,
                    'kind' => $kind,
                ];
            } elseif (isset($item['file_id']) && is_string($item['file_id']) && $item['file_id'] !== '') {
                $attachments[] = [
                    'telegram_file_id' => $item['file_id'],
                    'file_name' => $fileName,
                    'mime_type' => $mimeType,
                    'kind' => $kind,
                ];
            }
        }

        return $attachments;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{url: string, file_name: string, mime_type: string, kind?: string}>
     */
    private function extractVkAttachments(array $payload): array
    {
        $attachments = [];
        $vkMessage = $payload['object']['message'] ?? null;

        $vkAttachments = null;
        if (is_array($vkMessage) && isset($vkMessage['attachments']) && is_array($vkMessage['attachments'])) {
            $vkAttachments = $vkMessage['attachments'];
        } elseif (isset($payload['object']['attachments']) && is_array($payload['object']['attachments'])) {
            $vkAttachments = $payload['object']['attachments'];
        }

        if (! is_array($vkAttachments)) {
            return [];
        }

        foreach ($vkAttachments as $vkAttachment) {
            if (! is_array($vkAttachment)) {
                continue;
            }
            $type = $vkAttachment['type'] ?? null;
            if ($type === 'photo' && isset($vkAttachment['photo']['sizes'])) {
                $sizes = $vkAttachment['photo']['sizes'];
                $best = end($sizes);
                if (is_array($best) && isset($best['url'])) {
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

        return $attachments;
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
            $fileId = trim((string) ($attachment['telegram_file_id'] ?? ''));
            if ($url === '' && $fileId === '') {
                continue;
            }

            $signatureKey = $url !== '' ? $url : 'tg:'.$fileId;
            $signature = mb_strtolower($signatureKey.'|'.($attachment['kind'] ?? '').'|'.($attachment['file_name'] ?? ''));
            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $unique[] = $attachment;
        }

        return $unique;
    }

    /**
     * @param  list<array{url?: string, telegram_file_id?: string, file_name: string, mime_type: string, kind?: string}>  $attachments
     */
    public function buildAttachmentPlaceholderText(array $attachments): string
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
            'video' => '[Видео]',
            'video_note' => '[Видеосообщение]',
            'animation' => '[GIF]',
            default => '[Вложение]',
        };
    }
}

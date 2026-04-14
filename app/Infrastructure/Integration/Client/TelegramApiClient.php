<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Client;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Bot API client (HTTP). Used for sendMessage, sendPhoto, getFile, etc.
 */
final class TelegramApiClient
{
    public function __construct(
        private readonly string $botToken,
    ) {}

    private function apiBase(): string
    {
        return 'https://api.telegram.org/bot'.$this->botToken;
    }

    /**
     * @param  array<string, mixed>  $params  Supports reply_markup as list of {text,url}, reply_to_message_id.
     * @return array<string, mixed>
     */
    public function sendMessage(string $chatId, string $text, array $params = []): array
    {
        if ($this->botToken === '') {
            throw new \RuntimeException('Telegram bot token is empty');
        }

        $body = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if (isset($params['reply_to_message_id'])) {
            $body['reply_to_message_id'] = (int) $params['reply_to_message_id'];
        }

        if (isset($params['reply_markup']) && is_array($params['reply_markup'])) {
            $body['reply_markup'] = json_encode([
                'inline_keyboard' => array_map(
                    static fn (array $btn): array => [
                        [
                            'text' => $btn['text'],
                            'url' => $btn['url'],
                        ],
                    ],
                    $params['reply_markup'],
                ),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        $response = Http::timeout(60)->post($this->apiBase().'/sendMessage', $body);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Telegram sendMessage failed: '.$response->body(),
                $response->status(),
            );
        }

        $json = $response->json();
        if (! is_array($json) || ($json['ok'] ?? false) !== true) {
            throw new \RuntimeException('Telegram sendMessage invalid response: '.$response->body());
        }

        return $json;
    }

    /**
     * Send first attachment with caption (full text), others without caption. reply_to only on first outgoing.
     *
     * @param  list<string>  $absolutePaths
     * @param  array<string, mixed>  $params  reply_markup, reply_to_message_id
     */
    public function sendWithLocalFiles(string $chatId, string $text, array $absolutePaths, array $params = []): void
    {
        if ($this->botToken === '') {
            throw new \RuntimeException('Telegram bot token is empty');
        }

        $replyTo = isset($params['reply_to_message_id']) ? (int) $params['reply_to_message_id'] : null;
        $replyMarkup = null;
        if (isset($params['reply_markup']) && is_array($params['reply_markup'])) {
            $replyMarkup = json_encode([
                'inline_keyboard' => array_map(
                    static fn (array $btn): array => [
                        [
                            'text' => $btn['text'],
                            'url' => $btn['url'],
                        ],
                    ],
                    $params['reply_markup'],
                ),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        $first = true;
        foreach ($absolutePaths as $path) {
            if (! is_string($path) || ! is_file($path)) {
                continue;
            }

            $mime = @mime_content_type($path) ?: 'application/octet-stream';
            $isImage = str_starts_with((string) $mime, 'image/');

            $contents = @file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            $filename = basename($path);

            $multipart = [
                ['name' => 'chat_id', 'contents' => $chatId],
            ];

            if ($first && $replyTo !== null) {
                $multipart[] = ['name' => 'reply_to_message_id', 'contents' => (string) $replyTo];
            }

            if ($first && $replyMarkup !== null) {
                $multipart[] = ['name' => 'reply_markup', 'contents' => $replyMarkup];
            }

            if ($first) {
                $multipart[] = ['name' => 'caption', 'contents' => $text];
            }

            if ($isImage) {
                $multipart[] = [
                    'name' => 'photo',
                    'contents' => $contents,
                    'filename' => $filename,
                ];
                $response = Http::timeout(120)->asMultipart()->post($this->apiBase().'/sendPhoto', $multipart);
            } else {
                $multipart[] = [
                    'name' => 'document',
                    'contents' => $contents,
                    'filename' => $filename,
                ];
                $response = Http::timeout(120)->asMultipart()->post($this->apiBase().'/sendDocument', $multipart);
            }

            if (! $response->successful()) {
                throw new \RuntimeException('Telegram media send failed: '.$response->body());
            }
            $json = $response->json();
            if (! is_array($json) || ($json['ok'] ?? false) !== true) {
                throw new \RuntimeException('Telegram media send invalid response: '.$response->body());
            }

            $first = false;
        }

        if ($first === true) {
            $this->sendMessage($chatId, $text, $params);
        }
    }

    /**
     * Public HTTPS URL for a Telegram file (Bot API getFile).
     */
    public function getFileDownloadUrl(string $fileId): ?string
    {
        if ($this->botToken === '') {
            return null;
        }

        try {
            $response = Http::timeout(30)->get(
                $this->apiBase().'/getFile',
                ['file_id' => $fileId],
            );
            if (! $response->successful()) {
                return null;
            }
            $path = $response->json('result.file_path');
            if (! is_string($path) || $path === '') {
                return null;
            }

            return 'https://api.telegram.org/file/bot'.$this->botToken.'/'.$path;
        } catch (\Throwable $e) {
            Log::warning('Telegram getFile failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Best-effort profile photo URL for a user (getUserProfilePhotos + getFile).
     */
    public function getUserProfilePhotoUrl(int $userId): ?string
    {
        if ($this->botToken === '') {
            return null;
        }

        try {
            $response = Http::timeout(30)->get(
                $this->apiBase().'/getUserProfilePhotos',
                [
                    'user_id' => $userId,
                    'limit' => 1,
                ],
            );
            if (! $response->successful()) {
                return null;
            }
            $photos = $response->json('result.photos');
            if (! is_array($photos) || $photos === []) {
                return null;
            }
            $sizes = $photos[0] ?? null;
            if (! is_array($sizes) || $sizes === []) {
                return null;
            }
            $largest = end($sizes);
            if (! is_array($largest) || ! isset($largest['file_id']) || ! is_string($largest['file_id'])) {
                return null;
            }

            return $this->getFileDownloadUrl($largest['file_id']);
        } catch (\Throwable $e) {
            Log::warning('Telegram getUserProfilePhotos failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

            return null;
        }
    }
}

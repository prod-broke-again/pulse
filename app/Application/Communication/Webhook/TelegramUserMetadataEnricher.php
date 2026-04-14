<?php

declare(strict_types=1);

namespace App\Application\Communication\Webhook;

use App\Domains\Integration\ValueObject\SourceType;
use App\Infrastructure\Integration\Client\TelegramApiClient;
use Illuminate\Support\Facades\Log;

/**
 * Enriches Telegram guest metadata with profile photo URL when missing.
 */
final readonly class TelegramUserMetadataEnricher
{
    public function __construct(
        private UserMetadataMerger $userMetadataMerger,
    ) {}

    /**
     * @param  array<string, mixed>  $settings  Source settings (e.g. bot_token).
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $userMetadata
     * @return array<string, mixed>
     */
    public function enrich(
        SourceType $sourceType,
        array $settings,
        array $payload,
        array $userMetadata,
    ): array {
        if ($sourceType !== SourceType::Tg) {
            return $userMetadata;
        }

        $existing = isset($userMetadata['avatar_url']) && is_string($userMetadata['avatar_url'])
            ? trim($userMetadata['avatar_url'])
            : '';
        if ($existing !== '') {
            return $userMetadata;
        }

        $from = $payload['message']['from'] ?? null;
        if (! is_array($from) || ! isset($from['id']) || ! is_numeric($from['id'])) {
            return $userMetadata;
        }

        $userId = (int) $from['id'];
        if ($userId <= 0) {
            return $userMetadata;
        }

        $token = isset($settings['bot_token']) && is_string($settings['bot_token'])
            ? trim($settings['bot_token'])
            : '';
        if ($token === '') {
            return $userMetadata;
        }

        try {
            $client = new TelegramApiClient($token);
            $avatarUrl = $client->getUserProfilePhotoUrl($userId);
            if ($avatarUrl === null || $avatarUrl === '') {
                return $userMetadata;
            }

            return $this->userMetadataMerger->merge($userMetadata, ['avatar_url' => $avatarUrl]);
        } catch (\Throwable $e) {
            Log::warning('Telegram user metadata enrich failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return $userMetadata;
        }
    }
}

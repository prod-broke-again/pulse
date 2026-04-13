<?php

declare(strict_types=1);

namespace App\Application\Communication\Webhook;

use App\Domains\Integration\ValueObject\SourceType;
use App\Infrastructure\Integration\VkApiClientFactory;
use Illuminate\Support\Facades\Log;

/**
 * Fetches VK user profile when inbound metadata lacks a real display name.
 */
final readonly class VkUserMetadataEnricher
{
    public function __construct(
        private VkApiClientFactory $vkApiClientFactory,
        private UserMetadataMerger $userMetadataMerger,
    ) {}

    /**
     * @param  array<string, mixed>  $settings  Source settings (e.g. access_token).
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
        if ($sourceType !== SourceType::Vk) {
            return $userMetadata;
        }

        $currentName = isset($userMetadata['name']) && is_scalar($userMetadata['name'])
            ? trim((string) $userMetadata['name'])
            : '';
        if ($currentName !== '' && ! $this->userMetadataMerger->isPlaceholderGuestName($currentName)) {
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
            $profile = $this->vkApiClientFactory->make($token)->getUserProfile($fromId);
            if (! is_array($profile) || $profile === []) {
                return $userMetadata;
            }

            return $this->userMetadataMerger->merge($userMetadata, $profile);
        } catch (\Throwable $e) {
            Log::warning('VK users.get failed while enriching webhook metadata', [
                'from_id' => $fromId,
                'error' => $e->getMessage(),
            ]);

            return $userMetadata;
        }
    }
}

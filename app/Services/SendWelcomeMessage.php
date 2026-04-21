<?php

declare(strict_types=1);

namespace App\Services;

use App\Application\Integration\ResolveMessengerProvider;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Support\TelegramOutboundBusinessOptions;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

final readonly class SendWelcomeMessage
{
    public function __construct(
        private ResolveMessengerProvider $resolveMessenger,
        private CacheRepository $cache,
    ) {}

    public function run(SourceModel $source, string $externalUserId): void
    {
        $settings = is_array($source->settings) ? $source->settings : [];
        $enabled = (bool) ($settings['welcome_enabled'] ?? false);
        $text = trim((string) ($settings['welcome_text'] ?? ''));

        if (! $enabled || $text === '') {
            return;
        }

        $cacheKey = sprintf('welcome_sent:%d:%s', $source->id, $externalUserId);
        if (! $this->cache->add($cacheKey, true, now()->addMinutes(5))) {
            return;
        }

        try {
            $messenger = $this->resolveMessenger->run($source->id);
            $options = TelegramOutboundBusinessOptions::fromSourceSettingsOnly($settings);
            $messenger->sendMessage($externalUserId, $text, $options);
        } catch (\Throwable $exception) {
            Log::warning('Welcome message send failed', [
                'source_id' => $source->id,
                'external_user_id' => $externalUserId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

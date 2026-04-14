<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Messenger;

use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Infrastructure\Integration\Client\TelegramApiClient;
use Illuminate\Support\Facades\RateLimiter;

final class TelegramMessengerProvider implements MessengerProviderInterface
{
    private const SEND_RATE_PER_CHAT = 1;

    private const SEND_RATE_DECAY_SECONDS = 1;

    public function __construct(
        private TelegramApiClient $client,
    ) {}

    public function sendMessage(string $externalUserId, string $text, array $options = []): void
    {
        $key = 'telegram_send:'.$externalUserId;

        while (RateLimiter::tooManyAttempts($key, self::SEND_RATE_PER_CHAT)) {
            sleep(self::SEND_RATE_DECAY_SECONDS);
        }

        RateLimiter::hit($key, self::SEND_RATE_DECAY_SECONDS);

        $params = $options;
        unset($params['message_id']);

        $localPaths = [];
        if (isset($params['local_attachment_paths']) && is_array($params['local_attachment_paths'])) {
            $localPaths = array_values(array_filter(
                $params['local_attachment_paths'],
                static fn ($p): bool => is_string($p) && $p !== '',
            ));
        }
        unset($params['local_attachment_paths']);

        if (isset($params['reply_to_external_message_id'])) {
            $params['reply_to_message_id'] = (int) $params['reply_to_external_message_id'];
            unset($params['reply_to_external_message_id']);
        }

        if ($localPaths !== []) {
            $this->client->sendWithLocalFiles($externalUserId, $text, $localPaths, $params);

            return;
        }

        $this->client->sendMessage($externalUserId, $text, $params);
    }

    /** @param array<string, mixed> $payload */
    public function validateWebhook(array $payload): bool
    {
        return isset($payload['message']) || isset($payload['callback_query']);
    }
}

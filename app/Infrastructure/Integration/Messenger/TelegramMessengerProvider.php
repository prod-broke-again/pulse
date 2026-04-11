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

        /**
         * Pulse stores moderator inline URL buttons as:
         * `[{ "text": "...", "url": "https://..." }, ...]`
         * {@see TelegramApiClient} maps this to phptg/bot-api {@see \Phptg\BotApi\Type\InlineKeyboardMarkup}.
         *
         * @see https://core.telegram.org/bots/api#inlinekeyboardmarkup
         */
        $params = $options;
        // Internal correlation id for Pulse; not part of Telegram Bot API sendMessage.
        unset($params['message_id']);

        $this->client->sendMessage($externalUserId, $text, $params);
    }

    /** @param array<string, mixed> $payload */
    public function validateWebhook(array $payload): bool
    {
        return isset($payload['message']) || isset($payload['callback_query']);
    }
}

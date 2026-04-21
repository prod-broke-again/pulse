<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Messenger;

use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Infrastructure\Integration\Client\TelegramApiClient;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
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
        $pulseMessageId = isset($params['message_id']) ? (int) $params['message_id'] : null;
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
            $telegramMessageId = $this->client->sendWithLocalFiles($externalUserId, $text, $localPaths, $params);
            $this->persistOutboundTelegramMessageId($pulseMessageId, $telegramMessageId);

            return;
        }

        $json = $this->client->sendMessage($externalUserId, $text, $params);
        $telegramMessageId = $this->client->messageIdFromSendResponse($json);
        $this->persistOutboundTelegramMessageId($pulseMessageId, $telegramMessageId);
    }

    private function persistOutboundTelegramMessageId(?int $pulseMessageId, ?string $telegramMessageId): void
    {
        if ($pulseMessageId === null || $pulseMessageId <= 0 || $telegramMessageId === null || $telegramMessageId === '') {
            return;
        }

        MessageModel::query()->where('id', $pulseMessageId)->update(['external_message_id' => $telegramMessageId]);
    }

    /** @param array<string, mixed> $payload */
    public function validateWebhook(array $payload): bool
    {
        return isset($payload['message'])
            || isset($payload['callback_query'])
            || isset($payload['edited_message'])
            || isset($payload['channel_post'])
            || isset($payload['business_connection'])
            || isset($payload['business_message'])
            || isset($payload['edited_business_message'])
            || isset($payload['deleted_business_messages']);
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Messenger;

use App\Domains\Integration\Messenger\MessengerProviderInterface;

final class WebMessengerProvider implements MessengerProviderInterface
{
    public function sendMessage(string $externalUserId, string $text, array $options = []): void
    {
        // Web source: messages are delivered in-app via Reverb/API; no external send.
    }

    /** @param array<string, mixed> $payload */
    public function validateWebhook(array $payload): bool
    {
        return isset($payload['external_user_id']) && isset($payload['text']);
    }
}

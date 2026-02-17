<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Messenger;

use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Infrastructure\Integration\Client\VkApiClient;

final class VkMessengerProvider implements MessengerProviderInterface
{
    public function __construct(
        private VkApiClient $client,
    ) {}

    public function sendMessage(string $externalUserId, string $text, array $options = []): void
    {
        $this->client->sendMessage($externalUserId, $text, $options);
    }

    /** @param array<string, mixed> $payload */
    public function validateWebhook(array $payload): bool
    {
        return isset($payload['type']) && isset($payload['object']);
    }
}

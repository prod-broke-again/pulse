<?php

declare(strict_types=1);

namespace App\Domains\Integration\Messenger;

interface MessengerProviderInterface
{
    public function sendMessage(string $externalUserId, string $text, array $options = []): void;

    /** @param array<string, mixed> $payload */
    public function validateWebhook(array $payload): bool;
}

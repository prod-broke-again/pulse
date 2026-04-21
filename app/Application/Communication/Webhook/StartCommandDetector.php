<?php

declare(strict_types=1);

namespace App\Application\Communication\Webhook;

use App\Domains\Integration\ValueObject\SourceType;

final readonly class StartCommandDetector
{
    /** @param array<string, mixed> $payload */
    public function isStartCommand(SourceType $sourceType, array $payload): bool
    {
        return match ($sourceType) {
            SourceType::Tg, SourceType::Max => $this->isTelegramLikeStart($payload),
            SourceType::Vk => $this->isVkStart($payload),
            SourceType::Web => false,
        };
    }

    /** @param array<string, mixed> $payload */
    private function isTelegramLikeStart(array $payload): bool
    {
        $text = $payload['message']['text'] ?? null;
        if (! is_string($text)) {
            return false;
        }

        $trimmed = trim($text);
        if (! str_starts_with($trimmed, '/start')) {
            return false;
        }

        if ($trimmed === '/start') {
            return true;
        }

        $nextChar = substr($trimmed, 6, 1);

        return $nextChar === ' ' || $nextChar === '@';
    }

    /** @param array<string, mixed> $payload */
    private function isVkStart(array $payload): bool
    {
        $message = $payload['object']['message'] ?? null;
        if (! is_array($message)) {
            return false;
        }

        if ($this->isVkPayloadStart($message)) {
            return true;
        }

        $text = $message['text'] ?? null;
        if (! is_string($text)) {
            return false;
        }

        $normalized = mb_strtolower(trim($text));

        return in_array($normalized, ['начать', 'start', '/start'], true);
    }

    /** @param array<string, mixed> $message */
    private function isVkPayloadStart(array $message): bool
    {
        $rawPayload = $message['payload'] ?? null;
        if (! is_string($rawPayload) || trim($rawPayload) === '') {
            return false;
        }

        try {
            $decoded = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return is_array($decoded) && ($decoded['command'] ?? null) === 'start';
    }
}

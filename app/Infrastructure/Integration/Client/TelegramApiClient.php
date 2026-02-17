<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Client;

use Illuminate\Support\Facades\Http;

final class TelegramApiClient
{
    public function __construct(
        private string $botToken,
    ) {}

    private function baseUrl(): string
    {
        return "https://api.telegram.org/bot{$this->botToken}";
    }

    public function sendMessage(string $chatId, string $text, array $params = []): array
    {
        $response = Http::post($this->baseUrl() . '/sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            ...$params,
        ]);

        $response->throw();

        return $response->json();
    }
}

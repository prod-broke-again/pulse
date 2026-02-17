<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Client;

use Illuminate\Support\Facades\Http;

final class VkApiClient
{
    private const API_BASE = 'https://api.vk.com/method';

    public function __construct(
        private string $accessToken,
        private string $version = '5.199',
    ) {}

    public function sendMessage(string $userId, string $text, array $params = []): array
    {
        $response = Http::post(self::API_BASE . '/messages.send', [
            'access_token' => $this->accessToken,
            'v' => $this->version,
            'user_id' => $userId,
            'message' => $text,
            ...$params,
        ]);

        $response->throw();

        return $response->json();
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Client;

use VK\Client\VKApiClient as VKSDK;

/**
 * Wraps {@see VKSDK} (vkcom/vk-php-sdk) for messages.send and related calls.
 */
final class VkApiClient
{
    private readonly VKSDK $vk;

    public function __construct(
        private readonly string $accessToken,
        ?VKSDK $vk = null,
    ) {
        $this->vk = $vk ?? new VKSDK('5.199');
    }

    /**
     * @param  array<string, mixed>  $params  Merged into messages.send (e.g. keyboard).
     * @return array<string, mixed>
     */
    public function sendMessage(string $userId, string $text, array $params = []): array
    {
        $merged = array_merge([
            'user_id' => (int) $userId,
            'message' => $text,
            'random_id' => random_int(1, 2_100_000_000),
        ], $params);

        $response = $this->vk->messages()->send($this->accessToken, $merged);

        return is_array($response) ? $response : ['response' => $response];
    }
}

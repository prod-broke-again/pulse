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

    /**
     * @return array<string, scalar>|null
     */
    public function getUserProfile(int $userId): ?array
    {
        $response = $this->vk->users()->get($this->accessToken, [
            'user_ids' => [$userId],
            'fields' => ['screen_name', 'photo_100'],
        ]);

        if (! is_array($response) || ! isset($response[0]) || ! is_array($response[0])) {
            return null;
        }

        $user = $response[0];
        $firstName = isset($user['first_name']) && is_scalar($user['first_name'])
            ? trim((string) $user['first_name'])
            : '';
        $lastName = isset($user['last_name']) && is_scalar($user['last_name'])
            ? trim((string) $user['last_name'])
            : '';
        $screenName = isset($user['screen_name']) && is_scalar($user['screen_name'])
            ? trim((string) $user['screen_name'])
            : '';
        $avatarUrl = isset($user['photo_100']) && is_scalar($user['photo_100'])
            ? trim((string) $user['photo_100'])
            : '';
        $fullName = trim($firstName.' '.$lastName);

        $profile = [
            'id' => $userId,
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'username' => $screenName !== '' ? $screenName : null,
            'avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
            'name' => $fullName !== '' ? $fullName : null,
        ];

        return array_filter($profile, static fn ($value) => $value !== null && $value !== '');
    }
}

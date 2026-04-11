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
        $params = $options;
        unset($params['message_id']);

        if (isset($params['reply_markup']) && is_array($params['reply_markup'])) {
            $params['keyboard'] = $this->vkOpenLinkKeyboardJson($params['reply_markup']);
            unset($params['reply_markup']);
        }

        $this->client->sendMessage($externalUserId, $text, $params);
    }

    /**
     * @param  list<array{text: string, url: string}>  $buttons
     */
    private function vkOpenLinkKeyboardJson(array $buttons): string
    {
        $rows = [];
        foreach ($buttons as $btn) {
            $rows[] = [
                [
                    'action' => [
                        'type' => 'open_link',
                        'link' => $btn['url'],
                        'label' => $btn['text'],
                    ],
                ],
            ];
        }

        return json_encode([
            'one_time' => false,
            'inline' => true,
            'buttons' => $rows,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $payload */
    public function validateWebhook(array $payload): bool
    {
        return isset($payload['type']) && isset($payload['object']);
    }
}

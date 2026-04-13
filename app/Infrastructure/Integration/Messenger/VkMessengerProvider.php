<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Messenger;

use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Exceptions\MessengerDeliveryFailedException;
use App\Infrastructure\Integration\Client\VkApiClient;
use Illuminate\Support\Facades\Log;
use VK\Exceptions\VKApiException as VkApiException;

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

        try {
            $this->client->sendMessage($externalUserId, $text, $params);
        } catch (VkApiException $e) {
            Log::warning('VK messages.send failed', [
                'external_user_id' => $externalUserId,
                'vk_error_code' => $e->getErrorCode(),
                'vk_message' => $e->getMessage(),
            ]);

            throw new MessengerDeliveryFailedException(
                $this->friendlyMessageForVkError($e),
                $e,
            );
        }
    }

    private function friendlyMessageForVkError(VkApiException $e): string
    {
        return match ($e->getErrorCode()) {
            5, 15 => 'ВКонтакте: не настроен или недействителен токен доступа сообщества. Проверьте токен в настройках источника.',
            901 => 'ВКонтакте: пользователь запретил сообщения от сообщества.',
            945 => 'ВКонтакте: чат недоступен для отправки.',
            default => 'ВКонтакте: не удалось отправить сообщение ('.$e->getMessage().').',
        };
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

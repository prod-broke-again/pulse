<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Messenger;

use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Exceptions\MessengerDeliveryFailedException;
use App\Infrastructure\Integration\Client\VkApiClient;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
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
        $pulseMessageId = isset($params['message_id']) ? (int) $params['message_id'] : null;
        unset($params['message_id'], $params['local_attachment_paths'], $params['reply_to_external_message_id']);

        if (isset($params['reply_markup']) && is_array($params['reply_markup'])) {
            $params['keyboard'] = $this->buildKeyboardJson($params['reply_markup']);
            unset($params['reply_markup']);
        }

        try {
            $response = $this->client->sendMessage($externalUserId, $text, $params);
            $this->persistOutboundVkMessageId($pulseMessageId, $response);
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

    /**
     * @param  array<string, mixed>  $response
     */
    private function persistOutboundVkMessageId(?int $pulseMessageId, array $response): void
    {
        if ($pulseMessageId === null || $pulseMessageId <= 0) {
            return;
        }

        $rid = $response['response'] ?? null;
        if (is_int($rid)) {
            $ext = (string) $rid;
        } elseif (is_array($rid) && isset($rid['message_id'])) {
            $ext = (string) $rid['message_id'];
        } else {
            return;
        }

        if ($ext === '') {
            return;
        }

        MessageModel::query()->where('id', $pulseMessageId)->update(['external_message_id' => $ext]);
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
     * @param  list<array{text: string, url?: string, callback_data?: string}>  $buttons
     */
    private function buildKeyboardJson(array $buttons): string
    {
        $rows = [];
        foreach ($buttons as $btn) {
            if (isset($btn['url']) && is_string($btn['url']) && $btn['url'] !== '') {
                $rows[] = [
                    [
                        'action' => [
                            'type' => 'open_link',
                            'link' => $btn['url'],
                            'label' => $btn['text'],
                        ],
                    ],
                ];
            } elseif (isset($btn['callback_data']) && is_string($btn['callback_data']) && $btn['callback_data'] !== '') {
                $rows[] = [
                    [
                        'action' => [
                            'type' => 'callback',
                            'label' => $btn['text'],
                            'payload' => json_encode(['c' => $btn['callback_data']], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ];
            }
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

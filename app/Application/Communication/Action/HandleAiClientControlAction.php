<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Application\Integration\ResolveMessengerProvider;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\ValueObject\ChatStatus;
use App\Domains\Communication\ValueObject\SenderType;
use App\Infrastructure\Integration\Client\TelegramApiClient;
use App\Infrastructure\Integration\Client\VkApiClient;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Support\AiClientActionPayload;

/**
 * User clicked Решено / Не решено / Позвать человека on AI system message.
 */
final readonly class HandleAiClientControlAction
{
    public function __construct(
        private ChatRepositoryInterface $chatRepository,
        private SendMessage $sendMessage,
        private ResolveMessengerProvider $resolveMessenger,
    ) {}

    /**
     * @return array{ok: bool, error?: string}
     */
    public function run(
        int $sourceId,
        int $chatId,
        string $action,
    ): array {
        $chat = $this->chatRepository->findById($chatId);
        if ($chat === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if ($chat->sourceId !== $sourceId) {
            return ['ok' => false, 'error' => 'mismatch'];
        }

        if ($action === AiClientActionPayload::A_RESOLVED_YES) {
            $this->chatRepository->persist($chat->withOverrides([
                'status' => ChatStatus::Closed,
                'awaitingClientFeedback' => false,
            ]));

            return ['ok' => true];
        }

        if ($action === AiClientActionPayload::A_RESOLVED_NO
            || $action === AiClientActionPayload::A_HUMAN) {
            $this->chatRepository->persist($chat->withOverrides([
                'status' => ChatStatus::New,
                'assignedTo' => null,
                'awaitingClientFeedback' => false,
            ]));

            $messenger = $this->resolveMessenger->run($sourceId);
            $this->sendMessage->run(
                chatId: $chatId,
                text: 'Оператор скоро подключится.',
                senderType: SenderType::System,
                senderId: null,
                messenger: $messenger,
                deliverToMessenger: true,
            );

            return ['ok' => true];
        }

        return ['ok' => false, 'error' => 'bad_action'];
    }

    public function answerTelegramCallback(
        int $sourceId,
        string $callbackQueryId,
    ): void {
        $source = SourceModel::query()->find($sourceId);
        if ($source === null || $source->type !== 'tg') {
            return;
        }
        $token = isset($source->settings['bot_token']) && is_string($source->settings['bot_token'])
            ? trim($source->settings['bot_token'])
            : '';
        if ($token === '') {
            return;
        }
        (new TelegramApiClient($token))->answerCallbackQuery($callbackQueryId);
    }

    /**
     * @param  array{event_id: string, user_id: int, peer_id: int}  $vkEvent
     */
    public function answerVkEvent(int $sourceId, array $vkEvent): void
    {
        $source = SourceModel::query()->find($sourceId);
        if ($source === null || $source->type !== 'vk') {
            return;
        }
        $token = isset($source->settings['access_token']) && is_string($source->settings['access_token'])
            ? trim($source->settings['access_token'])
            : '';
        if ($token === '') {
            return;
        }
        (new VkApiClient($token))->sendMessageEventAnswer(
            (string) $vkEvent['event_id'],
            (int) $vkEvent['user_id'],
            (int) $vkEvent['peer_id'],
        );
    }
}

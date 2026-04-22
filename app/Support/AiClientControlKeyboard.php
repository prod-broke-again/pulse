<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Unified inline keyboard: URL buttons and callback buttons for AI controls.
 *
 * @phpstan-type ReplyMarkupButton array{
 *   text: string,
 *   url?: string,
 *   callback_data?: string,
 * }
 *
 * @return list<ReplyMarkupButton>
 */
final class AiClientControlKeyboard
{
    public static function forChat(int $chatId): array
    {
        return [
            [
                'text' => 'Позвать человека',
                'callback_data' => AiClientActionPayload::encode($chatId, AiClientActionPayload::A_HUMAN),
            ],
            [
                'text' => 'Решено',
                'callback_data' => AiClientActionPayload::encode($chatId, AiClientActionPayload::A_RESOLVED_YES),
            ],
            [
                'text' => 'Не решено',
                'callback_data' => AiClientActionPayload::encode($chatId, AiClientActionPayload::A_RESOLVED_NO),
            ],
        ];
    }
}

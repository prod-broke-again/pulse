<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Encodes / decodes short strings for callback buttons (Telegram 64 byte limit, VK json payload).
 */
final class AiClientActionPayload
{
    public const A_HUMAN = 'h';

    public const A_RESOLVED_YES = 'y';

    public const A_RESOLVED_NO = 'n';

    public static function encode(int $chatId, string $action): string
    {
        return 'p:'.$chatId.':'.$action;
    }

    /**
     * @return array{chat_id: int, action: string}|null
     */
    public static function parse(?string $data): ?array
    {
        if ($data === null) {
            return null;
        }
        $data = trim($data);
        if (! str_starts_with($data, 'p:')) {
            return null;
        }
        $parts = explode(':', $data, 3);
        if (count($parts) !== 3) {
            return null;
        }
        if (! is_numeric($parts[1])) {
            return null;
        }
        $action = $parts[2];
        if (! in_array($action, [self::A_HUMAN, self::A_RESOLVED_YES, self::A_RESOLVED_NO], true)) {
            return null;
        }

        return [
            'chat_id' => (int) $parts[1],
            'action' => $action,
        ];
    }
}

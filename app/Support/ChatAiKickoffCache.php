<?php

declare(strict_types=1);

namespace App\Support;

use App\Application\Ai\Dto\AiChatKickoffDto;
use App\Application\Ai\Dto\AiSuggestedReplyDto;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use Illuminate\Support\Facades\Cache;

/**
 * Результат одного kickoff-запроса к LLM при появлении темы чата; используется API summary/suggestions, пока в чат не пришли новые сообщения.
 */
final class ChatAiKickoffCache
{
    private const TTL_SECONDS = 604800;

    public static function key(int $chatId): string
    {
        return 'pulse:ai_chat_kickoff:'.$chatId;
    }

    public static function put(int $chatId, int $sealMessageId, AiChatKickoffDto $dto): void
    {
        Cache::put(self::key($chatId), [
            'seal_message_id' => $sealMessageId,
            'topic' => $dto->topic,
            'summary' => $dto->summary,
            'intent_tag' => $dto->intentTag,
            'replies' => array_map(
                static fn (AiSuggestedReplyDto $r): array => ['id' => $r->id, 'text' => $r->text],
                $dto->replies,
            ),
        ], self::TTL_SECONDS);
    }

    /**
     * @return array{seal_message_id: int, topic: ?string, summary: string, intent_tag: ?string, replies: list<array{id: string, text: string}>}|null
     */
    public static function getIfFresh(int $chatId): ?array
    {
        $maxId = (int) MessageModel::query()->where('chat_id', $chatId)->max('id');
        if ($maxId < 1) {
            return null;
        }
        $raw = Cache::get(self::key($chatId));
        if (! is_array($raw)) {
            return null;
        }
        if ((int) ($raw['seal_message_id'] ?? 0) !== $maxId) {
            return null;
        }

        return $raw;
    }
}

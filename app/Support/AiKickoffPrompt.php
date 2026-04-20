<?php

declare(strict_types=1);

namespace App\Support;

use App\Application\Ai\Dto\AiChatKickoffDto;
use App\Application\Ai\Dto\AiSuggestedReplyDto;
use Illuminate\Support\Str;

/**
 * Один JSON-ответ для старта чата: заголовок списка + превью для AI-панели + варианты ответов.
 */
final class AiKickoffPrompt
{
    public const SYSTEM = <<<'PROMPT'
Ты помощник модератора. Ниже — первые сообщения клиента (одна или несколько строк, только текст клиента).
Верни ОДИН JSON без markdown и без пояснений вне JSON. Структура строго:
{"topic":"…","summary":"…","intent_tag":"…","replies":[{"id":"r1","text":"…"},{"id":"r2","text":"…"}]}

Правила для topic (заголовок в списке чатов):
1) Длина строго от 2 до 5 слов. 2) Только суть, без кавычек и точек.
3) Если обращение общее (консультация, вопрос, уточнение, техподдержка без явной покупки) — topic «Общее».
4) Слова «Продажи», «Продажа» и близкие формулировки в topic используй ТОЛЬКО если клиент явно пишет о покупке, оплате, заказе, коммерческом предложении или оформлении сделки; иначе не используй их.

Правила для summary: 2–4 предложения на русском — кратко перескажи суть обращения для модератора.

Правила для intent_tag: одна короткая метка темы на русском.

Правила для replies: ровно 2 коротких варианта ответа клиенту на русском, разные по смыслу, вежливый тон.
PROMPT;

    public static function parse(string $content): AiChatKickoffDto
    {
        $content = trim($content);
        if ($content === '') {
            return new AiChatKickoffDto;
        }

        try {
            /** @var array{topic?: string, summary?: string, intent_tag?: string, replies?: list<array{id?: string, text?: string}>} $data */
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return new AiChatKickoffDto;
        }

        $topicRaw = isset($data['topic']) ? (string) $data['topic'] : '';
        $topic = self::cleanTopicLine($topicRaw);

        $summary = isset($data['summary']) ? trim((string) $data['summary']) : '';
        $intentTag = isset($data['intent_tag']) ? trim((string) $data['intent_tag']) : '';
        $intentTag = $intentTag !== '' ? $intentTag : null;

        $replies = [];
        foreach ($data['replies'] ?? [] as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = isset($row['id']) ? (string) $row['id'] : 'r'.($i + 1);
            $text = isset($row['text']) ? trim((string) $row['text']) : '';
            if ($text !== '') {
                $replies[] = new AiSuggestedReplyDto(id: $id, text: $text);
            }
        }

        return new AiChatKickoffDto(
            topic: $topic,
            summary: $summary,
            intentTag: $intentTag,
            replies: $replies,
        );
    }

    private static function cleanTopicLine(string $content): ?string
    {
        $topic = trim(preg_replace('/\s+/', ' ', $content));
        $topic = trim($topic, '"\'');
        if ($topic === '') {
            return null;
        }

        return Str::limit($topic, 255, '');
    }
}

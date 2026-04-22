<?php

declare(strict_types=1);

namespace App\Support;

use App\Application\Ai\Dto\AiChatKickoffDto;
use App\Application\Ai\Dto\AiSuggestedReplyDto;
use App\Domains\Integration\Entity\Department;
use Illuminate\Support\Str;

/**
 * Один JSON-ответ для старта чата: заголовок списка + превью для AI-панели + варианты ответов.
 */
final class AiKickoffPrompt
{
    public const SYSTEM = <<<'PROMPT'
Ты помощник службы поддержки. По первым сообщениям клиента ты должен:
1. Сформулировать кратко тему (topic, 2-5 слов).
2. Сделать короткое саммари (summary, 2-4 предложения на русском).
3. Определить одну короткую метку намерения (intent_tag).
4. Предложить 2 возможных ответа (replies, вежливый тон).
5. Выбрать наиболее подходящий департамент из ПРЕДОСТАВЛЕННОГО списка
   по его id. Если ни один не подходит — верни null. Новые департаменты
   НЕ создавай.
6. Оцени уверенность своего выбора числом от 0 до 1 (confidence).
7. Если вопрос типовой и ты МОЖЕШЬ дать готовый ответ клиенту (FAQ-стиль) —
   верни auto_reply_text (кратко, вежливо, по-русски) и auto_reply_confidence (0-1).
   Если не уверен, спорно, нет фактов, нужен человек — верни null для auto_reply_text.
8. Поле escalate_to_human: true, если вопрос сложный, рискованный (счета/мед/юр) или
   нельзя отвечать без модератора; тогда auto_reply_text должен быть null.

Верни ТОЛЬКО валидный JSON вида:
{
  "topic": "...",
  "summary": "...",
  "intent_tag": "...",
  "replies": [{"id":"r1","text":"..."},{"id":"r2","text":"..."}],
  "suggested_department_id": 5,
  "confidence": 0.92,
  "auto_reply_text": null,
  "auto_reply_confidence": null,
  "escalate_to_human": false
}

Правила:
- topic до 255 символов, по-русски.
- suggested_department_id — только из списка, переданного в user-сообщении.
  Если сомневаешься — верни null и confidence <= 0.5.
- confidence отражает, насколько уверенно ты выбрал департамент:
  0.9+ — очень уверенно, явное совпадение;
  0.7–0.9 — уверенно;
  0.5–0.7 — есть сомнения;
  < 0.5 — плохо подходит.
- auto_reply_text: только при очень высокой уверенности (рекомендуй auto_reply_confidence >= 0.85), иначе null.
- Никакого текста вне JSON.
PROMPT;

    /**
     * @param  list<Department>  $departments
     */
    public static function buildUserMessage(string $messagesText, array $departments): string
    {
        $list = [];
        foreach ($departments as $d) {
            if (! $d instanceof Department) {
                continue;
            }
            $list[] = [
                'id' => $d->id,
                'name' => $d->name,
                'category' => $d->category,
                'description' => self::categoryDescription($d->category),
            ];
        }

        $json = json_encode($list, JSON_UNESCAPED_UNICODE);

        return <<<TXT
Первые сообщения клиента:
---
{$messagesText}
---

Список департаментов (выбирай id только отсюда):
{$json}
TXT;
    }

    public static function parse(string $content): AiChatKickoffDto
    {
        $content = trim($content);
        if ($content === '') {
            return new AiChatKickoffDto;
        }

        try {
            /** @var array{topic?: string, summary?: string, intent_tag?: string, replies?: list<array{id?: string, text?: string}>, suggested_department_id?: int|null, confidence?: mixed, auto_reply_text?: string|null, auto_reply_confidence?: mixed, escalate_to_human?: bool} $data */
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
        $replies = array_slice($replies, 0, 2);

        $suggestedDepartmentId = null;
        if (array_key_exists('suggested_department_id', $data) && $data['suggested_department_id'] !== null) {
            $rawId = $data['suggested_department_id'];
            if (is_int($rawId)) {
                $suggestedDepartmentId = $rawId;
            } elseif (is_numeric($rawId)) {
                $suggestedDepartmentId = (int) $rawId;
            }
        }

        $confidence = null;
        if (array_key_exists('confidence', $data) && is_numeric($data['confidence'])) {
            $confidence = self::clampConfidence((float) $data['confidence']);
        }

        $escalateToHuman = (bool) ($data['escalate_to_human'] ?? false);
        if ($escalateToHuman) {
            $autoReplyText = null;
            $autoReplyConf = null;
        } else {
            $rawAuto = $data['auto_reply_text'] ?? null;
            $autoReplyText = is_string($rawAuto) && trim($rawAuto) !== '' ? trim($rawAuto) : null;
            if ($autoReplyText !== null && Str::length($autoReplyText) > 4000) {
                $autoReplyText = Str::limit($autoReplyText, 4000, '');
            }
            $autoReplyConf = null;
            if (array_key_exists('auto_reply_confidence', $data) && is_numeric($data['auto_reply_confidence'])) {
                $autoReplyConf = self::clampConfidence((float) $data['auto_reply_confidence']);
            }
        }

        return new AiChatKickoffDto(
            topic: $topic,
            summary: $summary,
            intentTag: $intentTag,
            replies: $replies,
            suggestedDepartmentId: $suggestedDepartmentId,
            confidence: $confidence,
            autoReplyText: $autoReplyText,
            autoReplyConfidence: $autoReplyConf,
            escalateToHuman: $escalateToHuman,
        );
    }

    private static function categoryDescription(string $category): string
    {
        return match ($category) {
            'support' => 'общие вопросы, заказы, оплата, доставка, сервис',
            'registration' => 'регистрация, учётная запись, доступ, верификация',
            'tech' => 'баги, настройка, интеграции, технические сбои',
            'ethics' => 'жалобы, этика, конфликтные ситуации',
            'other' => 'прочее, не подходит под другие категории',
            default => 'общее описание отдела',
        };
    }

    private static function clampConfidence(float $value): float
    {
        if ($value < 0) {
            return 0.0;
        }
        if ($value > 1) {
            return 1.0;
        }

        return $value;
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

<?php

declare(strict_types=1);

namespace App\Services;

use App\Application\Ai\Dto\AiSuggestedReplyDto;
use App\Application\Ai\Dto\AiThreadSummaryDto;
use App\Contracts\Ai\AiProviderInterface;
use App\Contracts\ChatTopicGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class GPTunnelService implements AiProviderInterface, ChatTopicGeneratorInterface
{
    private const ENDPOINT = 'https://gptunnel.ru/v1/chat/completions';

    private const TOPIC_SYSTEM_PROMPT = 'Ты помощник модератора. Твоя задача — прочитать первые сообщения клиента и составить ОЧЕНЬ краткий заголовок проблемы. Правила: 1. Длина строго от 2 до 5 слов. 2. Только суть проблемы. 3. Без кавычек и точек. 4. Ответь ТОЛЬКО заголовком.';

    private const SUMMARY_SYSTEM_PROMPT = 'Ты помощник модератора. По переписке верни JSON: {"summary":"краткое резюме на русском 2-4 предложения","intent_tag":"одна короткая метка темы на русском"}. Только JSON, без markdown.';

    private const SUGGEST_SYSTEM_PROMPT = 'Ты помощник модератора. По переписке предложи 2 коротких варианта ответа клиенту на русском. Верни JSON: {"replies":[{"id":"r1","text":"..."},{"id":"r2","text":"..."}]}. Только JSON.';

    public function generateTopic(string $messagesText): ?string
    {
        $content = $this->chatCompletion(self::TOPIC_SYSTEM_PROMPT, $messagesText, 50);

        return $content !== null ? $this->cleanTopic($content) : null;
    }

    public function summarizeThread(string $context): AiThreadSummaryDto
    {
        $content = $this->chatCompletion(self::SUMMARY_SYSTEM_PROMPT, $context, 400);
        if ($content === null || $content === '') {
            return new AiThreadSummaryDto(summary: '', intentTag: null);
        }

        try {
            /** @var array{summary?: string, intent_tag?: string} $data */
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return new AiThreadSummaryDto(
                summary: (string) ($data['summary'] ?? ''),
                intentTag: isset($data['intent_tag']) ? (string) $data['intent_tag'] : null,
            );
        } catch (\Throwable) {
            return new AiThreadSummaryDto(summary: Str::limit($content, 2000), intentTag: null);
        }
    }

    public function suggestReplies(string $context): array
    {
        $content = $this->chatCompletion(self::SUGGEST_SYSTEM_PROMPT, $context, 500);
        if ($content === null || $content === '') {
            return [];
        }

        try {
            /** @var array{replies?: list<array{id?: string, text?: string}>} $data */
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            $replies = $data['replies'] ?? [];
            $out = [];
            foreach ($replies as $i => $row) {
                $id = isset($row['id']) ? (string) $row['id'] : 'r'.($i + 1);
                $text = isset($row['text']) ? (string) $row['text'] : '';
                if ($text !== '') {
                    $out[] = new AiSuggestedReplyDto(id: $id, text: $text);
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    private function chatCompletion(string $systemPrompt, string $userContent, int $maxTokens): ?string
    {
        $apiKey = config('services.ai.gptunnel.api_key');

        if ($apiKey === null || $apiKey === '') {
            Log::debug('GPTunnelService: API key not set');

            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
            ])
                ->timeout(30)
                ->post(self::ENDPOINT, [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userContent],
                    ],
                    'max_tokens' => $maxTokens,
                    'useWalletBalance' => true,
                ]);

            if (! $response->successful()) {
                Log::warning('GPTunnelService: HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            return $data['choices'][0]['message']['content'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('GPTunnelService: request failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function cleanTopic(string $content): ?string
    {
        $topic = trim(preg_replace('/\s+/', ' ', $content));
        $topic = trim($topic, '"\'');
        if ($topic === '') {
            return null;
        }

        return $topic;
    }
}

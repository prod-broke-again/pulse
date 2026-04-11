<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ChatTopicGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TimewebAiService implements ChatTopicGeneratorInterface
{
    private const SYSTEM_PROMPT = "Ты помощник модератора. Твоя задача — прочитать первые сообщения клиента и составить ОЧЕНЬ краткий заголовок проблемы. Правила: 1. Длина строго от 2 до 5 слов. 2. Только суть проблемы. 3. Без кавычек и точек. 4. Ответь ТОЛЬКО заголовком.";

    public function generateTopic(string $messagesText): ?string
    {
        $apiKey = config('services.ai.timeweb.api_key');
        $baseUrl = rtrim((string) config('services.ai.timeweb.base_url', ''), '/');

        if ($apiKey === null || $apiKey === '' || $baseUrl === '') {
            Log::debug('TimewebAiService: API key or base URL not set');

            return null;
        }

        $url = $baseUrl . '/chat/completions';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post($url, [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $messagesText],
                    ],
                    'max_tokens' => 50,
                ]);

            if (! $response->successful()) {
                Log::warning('TimewebAiService: HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;

            return $content !== null ? $this->cleanTopic($content) : null;
        } catch (\Throwable $e) {
            Log::warning('TimewebAiService: request failed', ['error' => $e->getMessage()]);

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

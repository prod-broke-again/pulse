<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ChatTopicGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GPTunnelService implements ChatTopicGeneratorInterface
{
    private const ENDPOINT = 'https://gptunnel.ru/v1/chat/completions';

    private const SYSTEM_PROMPT = "Ты помощник модератора. Твоя задача — прочитать первые сообщения клиента и составить ОЧЕНЬ краткий заголовок проблемы. Правила: 1. Длина строго от 2 до 5 слов. 2. Только суть проблемы. 3. Без кавычек и точек. 4. Ответь ТОЛЬКО заголовком.";

    public function generateTopic(string $messagesText): ?string
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
                ->timeout(15)
                ->post(self::ENDPOINT, [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $messagesText],
                    ],
                    'max_tokens' => 50,
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
            $content = $data['choices'][0]['message']['content'] ?? null;

            return $content !== null ? $this->cleanTopic($content) : null;
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

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Ai\AiProviderInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Smoke-tests {@see AiProviderInterface} and optional raw chat completion for the configured provider.
 */
final class TestAiProviderCommand extends Command
{
    protected $signature = 'pulse:test-ai
                            {--prompt= : Свободный вопрос пользователю модели (сырой chat completion)}
                            {--topic= : Текст для generateTopic(); без опции — встроенный короткий пример}
                            {--summary= : Контекст для summarizeThread(); без опции — встроенный пример диалога}
                            {--suggest : Вызвать suggestReplies() с тем же контекстом, что и --summary}
                            {--model=gpt-4o-mini : Имя модели только для --prompt}
                            {--all : Прогнать topic + summary + suggest на встроенных примерах}';

    protected $description = 'Проверить AI (Timeweb / GPTunnel) по текущему services.ai.*';

    public function handle(AiProviderInterface $ai): int
    {
        $provider = (string) config('services.ai.default', 'timeweb');
        $this->info('AI provider: '.$provider);
        $this->printConfigHints($provider);
        $this->newLine();

        $prompt = $this->option('prompt');
        $prompt = is_string($prompt) ? trim($prompt) : '';

        if ($prompt !== '') {
            return $this->runRawPrompt($provider, $prompt);
        }

        if ($this->option('all')) {
            return $this->runAll($ai);
        }

        $topicOpt = $this->option('topic');
        $topicOpt = is_string($topicOpt) ? trim($topicOpt) : '';
        $summaryOpt = $this->option('summary');
        $summaryOpt = is_string($summaryOpt) ? trim($summaryOpt) : '';
        $wantSuggest = (bool) $this->option('suggest');

        if ($topicOpt !== '' || $summaryOpt !== '' || $wantSuggest) {
            if ($topicOpt !== '') {
                $this->runTopic($ai, $topicOpt);
            }
            $ctx = $summaryOpt !== '' ? $summaryOpt : self::sampleDialogue();
            if ($summaryOpt !== '' || $wantSuggest) {
                $this->runSummary($ai, $ctx);
            }
            if ($wantSuggest) {
                $this->runSuggest($ai, $ctx);
            }

            return self::SUCCESS;
        }

        $this->comment('Нет опций: прогоняю --all (topic + summary + suggest). Или: --prompt="Вопрос"');
        $this->newLine();

        return $this->runAll($ai);
    }

    private function printConfigHints(string $provider): void
    {
        if ($provider === 'gptunnel') {
            $key = config('services.ai.gptunnel.api_key');
            $this->line('  GPTUNNEL_API_KEY: '.($this->masked((string) $key)));

            return;
        }

        $key = config('services.ai.timeweb.api_key');
        $base = (string) config('services.ai.timeweb.base_url', '');
        $this->line('  TIMEWEB_AI_API_KEY: '.($this->masked((string) $key)));
        $this->line('  TIMEWEB_AI_BASE_URL: '.($base !== '' ? $base : '(empty)'));
    }

    private function masked(string $value): string
    {
        if ($value === '') {
            return '(empty)';
        }
        if (strlen($value) <= 8) {
            return '***';
        }

        return substr($value, 0, 4).'…'.substr($value, -4);
    }

    private function runAll(AiProviderInterface $ai): int
    {
        $this->runTopic($ai, 'Здравствуйте, не могу войти в личный кабинет, пишет неверный пароль.');
        $this->newLine();
        $ctx = self::sampleDialogue();
        $this->runSummary($ai, $ctx);
        $this->newLine();
        $this->runSuggest($ai, $ctx);

        return self::SUCCESS;
    }

    private function runTopic(AiProviderInterface $ai, string $text): void
    {
        $this->info('generateTopic()');
        $started = microtime(true);
        $topic = $ai->generateTopic($text);
        $ms = round((microtime(true) - $started) * 1000, 1);
        if ($topic === null || $topic === '') {
            $this->warn("  (пусто, за {$ms} ms) — проверьте ключ, URL и логи TimewebAiService / GPTunnelService");

            return;
        }
        $this->line("  ({$ms} ms) {$topic}");
    }

    private function runSummary(AiProviderInterface $ai, string $context): void
    {
        $this->info('summarizeThread()');
        $started = microtime(true);
        $dto = $ai->summarizeThread($context);
        $ms = round((microtime(true) - $started) * 1000, 1);
        if ($dto->summary === '' && $dto->intentTag === null) {
            $this->warn("  (пусто, за {$ms} ms)");

            return;
        }
        $this->line("  ({$ms} ms) summary: {$dto->summary}");
        $this->line('  intent_tag: '.($dto->intentTag ?? '(null)'));
    }

    private function runSuggest(AiProviderInterface $ai, string $context): void
    {
        $this->info('suggestReplies()');
        $started = microtime(true);
        $replies = $ai->suggestReplies($context);
        $ms = round((microtime(true) - $started) * 1000, 1);
        if ($replies === []) {
            $this->warn("  (нет вариантов, за {$ms} ms)");

            return;
        }
        $this->line("  ({$ms} ms)");
        foreach ($replies as $r) {
            $this->line("  [{$r->id}] {$r->text}");
        }
    }

    private function runRawPrompt(string $provider, string $prompt): int
    {
        $model = (string) $this->option('model');
        $model = trim($model) !== '' ? trim($model) : 'gpt-4o-mini';

        $this->info('Raw chat completion');
        $started = microtime(true);

        try {
            if ($provider === 'gptunnel') {
                $apiKey = (string) config('services.ai.gptunnel.api_key', '');
                if ($apiKey === '') {
                    $this->error('GPTUNNEL_API_KEY пуст.');

                    return self::FAILURE;
                }
                $response = Http::withHeaders(['Authorization' => $apiKey])
                    ->timeout(60)
                    ->post('https://gptunnel.ru/v1/chat/completions', [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'max_tokens' => 256,
                        'useWalletBalance' => true,
                    ]);
            } else {
                $apiKey = (string) config('services.ai.timeweb.api_key', '');
                $baseUrl = rtrim((string) config('services.ai.timeweb.base_url', ''), '/');
                if ($apiKey === '' || $baseUrl === '') {
                    $this->error('TIMEWEB_AI_API_KEY или TIMEWEB_AI_BASE_URL пусты.');

                    return self::FAILURE;
                }
                $response = Http::withToken($apiKey)
                    ->timeout(60)
                    ->post($baseUrl.'/chat/completions', [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'max_tokens' => 256,
                    ]);
            }
        } catch (\Throwable $e) {
            $this->error('Запрос не удался: '.$e->getMessage());

            return self::FAILURE;
        }

        $ms = round((microtime(true) - $started) * 1000, 1);

        if (! $response->successful()) {
            $this->error("HTTP {$response->status()} за {$ms} ms");
            $body = $response->body();
            $this->line(strlen($body) > 1200 ? substr($body, 0, 1200).'…' : $body);

            return self::FAILURE;
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? null;
        if (! is_string($content) || $content === '') {
            $this->warn("Ответ без текста за {$ms} ms");
            $this->line(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '');

            return self::FAILURE;
        }

        $this->info("Ответ за {$ms} ms:");
        $this->line($content);

        return self::SUCCESS;
    }

    private static function sampleDialogue(): string
    {
        return <<<'TXT'
client: Добрый день, оплатил подписку, но доступ не открылся.
moderator: Здравствуйте. Подскажите email аккаунта и дату платежа.
client: ivan@example.com, вчера вечером, карта МИР.
TXT;
    }
}

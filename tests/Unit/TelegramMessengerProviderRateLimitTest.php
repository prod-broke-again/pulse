<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Integration\Client\TelegramApiClient;
use App\Infrastructure\Integration\Messenger\TelegramMessengerProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TelegramMessengerProviderRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1],
            ]),
        ]);
    }

    public function test_send_message_calls_telegram_api(): void
    {
        $client = new TelegramApiClient('test_bot_token');
        $provider = new TelegramMessengerProvider($client);
        $provider->sendMessage('chat_123', 'Hello', []);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'api.telegram.org')
                && str_contains($request->url(), 'sendMessage');
        });
    }

    public function test_rate_limiter_allows_multiple_sends(): void
    {
        $client = new TelegramApiClient('test_bot_token');
        $provider = new TelegramMessengerProvider($client);
        $provider->sendMessage('chat_456', 'First', []);
        $provider->sendMessage('chat_456', 'Second', []);

        Http::assertSentCount(2);
    }
}

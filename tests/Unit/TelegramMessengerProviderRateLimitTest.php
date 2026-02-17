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
    }

    public function test_send_message_calls_telegram_api(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $client = new TelegramApiClient('test_bot_token');
        $provider = new TelegramMessengerProvider($client);
        $provider->sendMessage('chat_123', 'Hello', []);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($request->url(), 'sendMessage')
                && ($body['chat_id'] ?? null) === 'chat_123'
                && ($body['text'] ?? null) === 'Hello';
        });
    }

    public function test_rate_limiter_allows_multiple_sends(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $client = new TelegramApiClient('test_bot_token');
        $provider = new TelegramMessengerProvider($client);
        $provider->sendMessage('chat_456', 'First', []);
        $provider->sendMessage('chat_456', 'Second', []);

        Http::assertSentCount(2);
    }
}

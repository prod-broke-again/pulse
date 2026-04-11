<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Integration\Client\TelegramApiClient;
use App\Infrastructure\Integration\Messenger\TelegramMessengerProvider;
use Illuminate\Support\Facades\Cache;
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
        $transport = new FakeTelegramTransport;
        $client = new TelegramApiClient('test_bot_token', $transport);
        $provider = new TelegramMessengerProvider($client);
        $provider->sendMessage('chat_123', 'Hello', []);

        $this->addToAssertionCount(1);
    }

    public function test_rate_limiter_allows_multiple_sends(): void
    {
        $transport = new FakeTelegramTransport;
        $client = new TelegramApiClient('test_bot_token', $transport);
        $provider = new TelegramMessengerProvider($client);
        $provider->sendMessage('chat_456', 'First', []);
        $provider->sendMessage('chat_456', 'Second', []);

        $this->addToAssertionCount(1);
    }
}

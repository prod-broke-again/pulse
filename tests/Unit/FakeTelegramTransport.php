<?php

declare(strict_types=1);

namespace Tests\Unit;

use Phptg\BotApi\Transport\ApiResponse;
use Phptg\BotApi\Transport\TransportInterface;

/**
 * Returns minimal valid Telegram Bot API JSON for unit tests (no real HTTP).
 */
final class FakeTelegramTransport implements TransportInterface
{
    public function get(string $url): ApiResponse
    {
        return new ApiResponse(200, '{"ok":true,"result":[]}');
    }

    public function post(string $url, string $body, array $headers): ApiResponse
    {
        return new ApiResponse(200, '{"ok":true,"result":{"message_id":1,"chat":{"id":1,"type":"private"},"date":0,"text":"x"}}');
    }

    public function postWithFiles(string $url, array $data, array $files): ApiResponse
    {
        return $this->post($url, '', []);
    }

    public function downloadFile(string $url): mixed
    {
        $h = fopen('php://memory', 'r+b');
        if ($h === false) {
            throw new \RuntimeException('fopen failed');
        }

        return $h;
    }
}

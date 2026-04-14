<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Client;

use Phptg\BotApi\FailResult;
use Phptg\BotApi\TelegramBotApi;
use Phptg\BotApi\Transport\CurlTransport;
use Phptg\BotApi\Transport\TransportInterface;
use Phptg\BotApi\Type\InlineKeyboardButton;
use Phptg\BotApi\Type\InlineKeyboardMarkup;

/**
 * Thin wrapper around {@see TelegramBotApi} (Composer: phptg/bot-api).
 */
final class TelegramApiClient
{
    private TelegramBotApi $api;

    public function __construct(
        private readonly string $botToken,
        ?TransportInterface $transport = null,
    ) {
        $this->api = new TelegramBotApi(
            $this->botToken,
            // Curl transport is more resilient than PHP streams in production networking conditions.
            transport: $transport ?? new CurlTransport,
        );
    }

    /**
     * @param  array<string, mixed>  $params  Optional extras; supports `reply_markup` as list of `{text, url}` for inline URL buttons.
     * @return array<string, mixed>
     */
    public function sendMessage(string $chatId, string $text, array $params = []): array
    {
        $replyMarkup = null;
        if (isset($params['reply_markup']) && is_array($params['reply_markup'])) {
            /** @var list<array{text: string, url: string}> $rows */
            $rows = $params['reply_markup'];
            $replyMarkup = new InlineKeyboardMarkup(
                array_map(
                    static fn (array $btn): array => [
                        new InlineKeyboardButton($btn['text'], url: $btn['url']),
                    ],
                    $rows,
                ),
            );
            unset($params['reply_markup']);
        }

        $result = $this->api->sendMessage(
            $chatId,
            $text,
            replyMarkup: $replyMarkup,
        );

        if ($result instanceof FailResult) {
            throw new \RuntimeException(
                $result->description ?? 'Telegram Bot API error',
                (int) ($result->errorCode ?? 0),
            );
        }

        return ['ok' => true];
    }
}

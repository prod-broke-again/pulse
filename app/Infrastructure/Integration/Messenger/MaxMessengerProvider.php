<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Messenger;

use App\Domains\Integration\Messenger\MessengerProviderInterface;
use TH\MAX\Client\DTO\Messages\Attachments\Buttons\LinkButton;
use TH\MAX\Client\DTO\Messages\Attachments\InlineKeyboardAttachment;
use TH\MAX\Client\DTO\Messages\Attachments\Payload\InlineKeyboardPayload;
use TH\MAX\Client\MAXClient;

/**
 * Outbound messages via {@see MAXClient} (targethunter/max-php-sdk).
 */
final class MaxMessengerProvider implements MessengerProviderInterface
{
    public function __construct(
        private readonly MAXClient $client,
    ) {}

    public function sendMessage(string $externalUserId, string $text, array $options = []): void
    {
        unset($options['message_id']);

        /** @var list<array{text: string, url: string}>|null $replyMarkup */
        $replyMarkup = null;
        if (isset($options['reply_markup']) && is_array($options['reply_markup'])) {
            $replyMarkup = $options['reply_markup'];
        }
        unset($options['reply_markup']);

        $attachments = null;
        if ($replyMarkup !== null && $replyMarkup !== []) {
            $attachments = [$this->inlineKeyboardAttachment($replyMarkup)];
        }

        $this->client->messages()->send(
            user_id: (int) $externalUserId,
            chat_id: null,
            text: $text,
            attachments: $attachments,
        );
    }

    /**
     * @param  list<array{text: string, url: string}>  $buttons
     */
    private function inlineKeyboardAttachment(array $buttons): InlineKeyboardAttachment
    {
        $rows = [];
        foreach ($buttons as $btn) {
            $rows[] = [
                new LinkButton([
                    'text' => $btn['text'],
                    'url' => $btn['url'],
                ]),
            ];
        }

        return new InlineKeyboardAttachment([
            'payload' => new InlineKeyboardPayload([
                'buttons' => $rows,
            ]),
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    public function validateWebhook(array $payload): bool
    {
        return $payload !== [];
    }
}

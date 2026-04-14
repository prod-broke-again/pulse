<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Communication\Webhook\WebhookPayloadExtractor;
use Tests\TestCase;

final class WebhookPayloadExtractorTelegramTest extends TestCase
{
    public function test_extract_telegram_media_group_id(): void
    {
        $extractor = app(WebhookPayloadExtractor::class);

        $payload = [
            'message' => [
                'message_id' => 55,
                'media_group_id' => 12345,
                'from' => ['id' => 1],
            ],
        ];

        $this->assertSame('12345', $extractor->extractTelegramMediaGroupId($payload));
    }

    public function test_extract_raw_telegram_message_text_from_caption(): void
    {
        $extractor = app(WebhookPayloadExtractor::class);

        $payload = [
            'message' => [
                'caption' => '  Pic note  ',
                'from' => ['id' => 1],
            ],
        ];

        $this->assertSame('Pic note', $extractor->extractRawTelegramMessageText($payload));
    }
}

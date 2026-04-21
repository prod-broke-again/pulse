<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Communication\Webhook\WebhookPayloadExtractor;
use Tests\TestCase;

final class WebhookPayloadExtractorBusinessTest extends TestCase
{
    public function test_extract_external_user_id_from_business_message(): void
    {
        $extractor = app(WebhookPayloadExtractor::class);

        $payload = [
            'business_message' => [
                'from' => ['id' => 12345],
            ],
        ];

        $this->assertSame('12345', $extractor->extractExternalUserId($payload));
    }

    public function test_extract_text_from_business_message(): void
    {
        $extractor = app(WebhookPayloadExtractor::class);

        $payload = [
            'business_message' => [
                'text' => 'hello',
            ],
        ];

        $this->assertSame('hello', $extractor->extractText($payload, []));
    }

    public function test_extract_external_message_id_from_business_message(): void
    {
        $extractor = app(WebhookPayloadExtractor::class);

        $payload = [
            'business_message' => [
                'message_id' => 777,
            ],
        ];

        $this->assertSame('777', $extractor->extractExternalMessageId($payload));
    }

    public function test_extract_business_connection_id(): void
    {
        $extractor = app(WebhookPayloadExtractor::class);

        $with = [
            'business_message' => [
                'business_connection_id' => 'abc',
            ],
        ];
        $this->assertSame('abc', $extractor->extractBusinessConnectionId($with));

        $this->assertNull($extractor->extractBusinessConnectionId(['message' => []]));
    }

    public function test_extract_user_metadata_from_business_message_from(): void
    {
        $extractor = app(WebhookPayloadExtractor::class);

        $payload = [
            'business_message' => [
                'from' => [
                    'id' => 1,
                    'first_name' => 'Ann',
                    'last_name' => 'Bee',
                ],
            ],
        ];

        $meta = $extractor->extractUserMetadata($payload);
        $this->assertSame('Ann Bee', $meta['name'] ?? null);
    }

    public function test_extract_private_chat_peer_id_for_business_message(): void
    {
        $extractor = app(WebhookPayloadExtractor::class);

        $payload = [
            'business_message' => [
                'from' => ['id' => 100],
                'chat' => [
                    'id' => 200,
                    'type' => 'private',
                    'first_name' => 'Peer',
                ],
            ],
        ];

        $this->assertSame('200', $extractor->extractTelegramBusinessMessagePrivateChatPeerExternalUserId($payload));
        $meta = $extractor->extractTelegramBusinessMessageChatPeerUserMetadata($payload);
        $this->assertSame(200, $meta['id'] ?? null);
        $this->assertSame('Peer', $meta['name'] ?? null);
    }

    public function test_extract_private_chat_peer_null_for_non_private(): void
    {
        $extractor = app(WebhookPayloadExtractor::class);

        $payload = [
            'business_message' => [
                'chat' => ['id' => -100123, 'type' => 'supergroup', 'title' => 'X'],
            ],
        ];

        $this->assertNull($extractor->extractTelegramBusinessMessagePrivateChatPeerExternalUserId($payload));
    }
}

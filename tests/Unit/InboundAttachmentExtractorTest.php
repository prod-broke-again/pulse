<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Communication\Webhook\InboundAttachmentExtractor;
use Tests\TestCase;

final class InboundAttachmentExtractorTest extends TestCase
{
    public function test_extracts_telegram_sticker_with_file_id(): void
    {
        $extractor = new InboundAttachmentExtractor;
        $payload = [
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'sticker' => [
                    'file_id' => 'AgADBAADY',
                    'file_unique_id' => 'uniq_st',
                    'is_animated' => false,
                    'is_video' => false,
                ],
            ],
        ];

        $out = $extractor->extract($payload);

        $this->assertCount(1, $out);
        $this->assertSame('sticker', $out[0]['kind'] ?? null);
        $this->assertSame('AgADBAADY', $out[0]['telegram_file_id'] ?? null);
        $this->assertSame('image/webp', $out[0]['mime_type']);
        $this->assertSame('sticker_uniq_st.webp', $out[0]['file_name']);
    }

    public function test_extracts_telegram_sticker_video_kind(): void
    {
        $extractor = new InboundAttachmentExtractor;
        $payload = [
            'message' => [
                'sticker' => [
                    'file_id' => 'vid',
                    'file_unique_id' => 'u1',
                    'is_video' => true,
                ],
            ],
        ];

        $out = $extractor->extract($payload);

        $this->assertCount(1, $out);
        $this->assertSame('sticker', $out[0]['kind'] ?? null);
        $this->assertSame('video/webm', $out[0]['mime_type']);
        $this->assertStringEndsWith('.webm', $out[0]['file_name']);
    }

    public function test_extracts_telegram_voice_photo_and_video(): void
    {
        $extractor = new InboundAttachmentExtractor;
        $payload = [
            'message' => [
                'voice' => [
                    'file_id' => 'voice_id',
                    'file_unique_id' => 'v',
                    'mime_type' => 'audio/ogg',
                ],
                'photo' => [
                    ['file_id' => 'small', 'width' => 1, 'height' => 1],
                    ['file_id' => 'large', 'width' => 1280, 'height' => 720],
                ],
                'video' => [
                    'file_id' => 'video_id',
                    'file_unique_id' => 'vid',
                    'mime_type' => 'video/mp4',
                ],
            ],
        ];

        $out = $extractor->extract($payload);

        $byKind = [];
        foreach ($out as $row) {
            $byKind[$row['kind'] ?? ''] = $row;
        }
        $this->assertSame('voice_id', $byKind['voice']['telegram_file_id'] ?? null);
        $this->assertSame('large', $byKind['photo']['telegram_file_id'] ?? null);
        $this->assertSame('video_id', $byKind['video']['telegram_file_id'] ?? null);
    }

    public function test_extracts_video_note_and_animation(): void
    {
        $extractor = new InboundAttachmentExtractor;
        $payload = [
            'message' => [
                'video_note' => [
                    'file_id' => 'vn1',
                    'file_unique_id' => 'vn',
                ],
                'animation' => [
                    'file_id' => 'anim1',
                    'file_unique_id' => 'a',
                    'mime_type' => 'video/mp4',
                ],
            ],
        ];

        $out = $extractor->extract($payload);

        $this->assertCount(2, $out);
        $byKind = [];
        foreach ($out as $row) {
            $byKind[$row['kind'] ?? ''] = $row;
        }
        $this->assertSame('vn1', $byKind['video_note']['telegram_file_id'] ?? null);
        $this->assertSame('anim1', $byKind['animation']['telegram_file_id'] ?? null);
    }

    public function test_extract_uses_edited_message_when_message_absent(): void
    {
        $extractor = new InboundAttachmentExtractor;
        $payload = [
            'update_id' => 2,
            'edited_message' => [
                'message_id' => 5,
                'sticker' => [
                    'file_id' => 'st',
                    'file_unique_id' => 'e',
                    'is_animated' => false,
                    'is_video' => false,
                ],
            ],
        ];

        $out = $extractor->extract($payload);

        $this->assertCount(1, $out);
        $this->assertSame('sticker', $out[0]['kind'] ?? null);
    }

    public function test_build_attachment_placeholder_text_single_kinds(): void
    {
        $extractor = new InboundAttachmentExtractor;

        $this->assertSame('[Стикер]', $extractor->buildAttachmentPlaceholderText([
            ['kind' => 'sticker', 'file_name' => 'x', 'mime_type' => 'image/webp'],
        ]));
        $this->assertSame('[Видео]', $extractor->buildAttachmentPlaceholderText([
            ['kind' => 'video', 'file_name' => 'x', 'mime_type' => 'video/mp4'],
        ]));
        $this->assertSame('[Видеосообщение]', $extractor->buildAttachmentPlaceholderText([
            ['kind' => 'video_note', 'file_name' => 'x', 'mime_type' => 'video/mp4'],
        ]));
        $this->assertSame('[GIF]', $extractor->buildAttachmentPlaceholderText([
            ['kind' => 'animation', 'file_name' => 'x', 'mime_type' => 'video/mp4'],
        ]));
        $this->assertSame('[Вложений: 2]', $extractor->buildAttachmentPlaceholderText([
            ['kind' => 'photo', 'file_name' => 'a', 'mime_type' => 'image/jpeg'],
            ['kind' => 'photo', 'file_name' => 'b', 'mime_type' => 'image/jpeg'],
        ]));
    }
}

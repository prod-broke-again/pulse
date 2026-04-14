<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Communication\Webhook\TelegramMediaGroupInboundBuffer;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Jobs\DownloadInboundAttachmentJob;
use App\Jobs\ProcessTelegramMediaGroupJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class TelegramMediaGroupInboundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_media_group_job_merges_fragments_into_one_message(): void
    {
        config(['pulse.telegram_media_group_quiet_ms' => 0]);

        $source = SourceModel::create([
            'name' => 'TG',
            'type' => 'tg',
            'identifier' => 'tg_bot',
            'secret_key' => null,
            'settings' => [],
        ]);
        $department = DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);
        $chat = ChatModel::create([
            'source_id' => $source->id,
            'department_id' => $department->id,
            'external_user_id' => '999001',
            'user_metadata' => [],
            'status' => 'new',
            'assigned_to' => null,
        ]);

        $bufKey = TelegramMediaGroupInboundBuffer::bufferKey($source->id, $chat->id, '42');
        Cache::put($bufKey, [
            'fragments' => [
                [
                    'attachments' => [
                        ['url' => 'https://example.com/one.jpg', 'file_name' => 'one.jpg', 'mime_type' => 'image/jpeg', 'kind' => 'photo'],
                    ],
                    'raw_caption' => '',
                    'reply_to_message_id' => null,
                    'telegram_message_id' => '101',
                ],
                [
                    'attachments' => [
                        ['url' => 'https://example.com/two.jpg', 'file_name' => 'two.jpg', 'mime_type' => 'image/jpeg', 'kind' => 'photo'],
                    ],
                    'raw_caption' => '',
                    'reply_to_message_id' => null,
                    'telegram_message_id' => '102',
                ],
            ],
            'updated_at' => microtime(true) - 2,
        ], 300);

        $job = new ProcessTelegramMediaGroupJob($source->id, $chat->id, '42');
        $job->handle(
            app(\App\Application\Communication\Action\CreateMessage::class),
            app(\App\Application\Communication\Webhook\InboundAttachmentExtractor::class),
            app(\App\Services\MaybeSendOfflineAutoReply::class),
        );

        $this->assertSame(1, MessageModel::where('chat_id', $chat->id)->count());
        $message = MessageModel::where('chat_id', $chat->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('mg:42', $message->external_message_id);
        $this->assertSame('[Вложений: 2]', $message->text);
        $payload = $message->payload ?? [];
        $this->assertSame('42', $payload['telegram_media_group_id'] ?? null);
        $this->assertSame(['101', '102'], $payload['telegram_message_ids'] ?? null);
    }

    public function test_media_group_external_id_is_idempotent(): void
    {
        config(['pulse.telegram_media_group_quiet_ms' => 0]);

        $source = SourceModel::create([
            'name' => 'TG2',
            'type' => 'tg',
            'identifier' => 'tg_bot2',
            'secret_key' => null,
            'settings' => [],
        ]);
        $department = DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support2',
            'is_active' => true,
        ]);
        $chat = ChatModel::create([
            'source_id' => $source->id,
            'department_id' => $department->id,
            'external_user_id' => '999002',
            'user_metadata' => [],
            'status' => 'new',
            'assigned_to' => null,
        ]);

        $bufKey = TelegramMediaGroupInboundBuffer::bufferKey($source->id, $chat->id, '7');
        $fragment = [
            'attachments' => [
                ['url' => 'https://example.com/a.jpg', 'file_name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'kind' => 'photo'],
            ],
            'raw_caption' => 'Hello album',
            'reply_to_message_id' => null,
            'telegram_message_id' => '201',
        ];
        Cache::put($bufKey, ['fragments' => [$fragment], 'updated_at' => microtime(true) - 2], 300);

        $job = new ProcessTelegramMediaGroupJob($source->id, $chat->id, '7');
        $deps = [
            app(\App\Application\Communication\Action\CreateMessage::class),
            app(\App\Application\Communication\Webhook\InboundAttachmentExtractor::class),
            app(\App\Services\MaybeSendOfflineAutoReply::class),
        ];
        $job->handle(...$deps);

        Cache::put($bufKey, ['fragments' => [$fragment], 'updated_at' => microtime(true) - 2], 300);
        $job->handle(...$deps);

        $this->assertSame(1, MessageModel::where('chat_id', $chat->id)->count());
        $this->assertSame('Hello album', MessageModel::where('chat_id', $chat->id)->value('text'));
    }

    public function test_media_group_job_truncates_merged_attachments_to_config_max(): void
    {
        Queue::fake();

        config([
            'pulse.telegram_media_group_quiet_ms' => 0,
            'pulse.telegram_media_group_max_items' => 3,
        ]);

        $source = SourceModel::create([
            'name' => 'TG3',
            'type' => 'tg',
            'identifier' => 'tg_bot3',
            'secret_key' => null,
            'settings' => [],
        ]);
        $department = DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support3',
            'is_active' => true,
        ]);
        $chat = ChatModel::create([
            'source_id' => $source->id,
            'department_id' => $department->id,
            'external_user_id' => '999003',
            'user_metadata' => [],
            'status' => 'new',
            'assigned_to' => null,
        ]);

        $fragments = [];
        for ($i = 1; $i <= 5; $i++) {
            $fragments[] = [
                'attachments' => [
                    ['url' => "https://example.com/p{$i}.jpg", 'file_name' => "p{$i}.jpg", 'mime_type' => 'image/jpeg', 'kind' => 'photo'],
                ],
                'raw_caption' => '',
                'reply_to_message_id' => null,
                'telegram_message_id' => (string) (300 + $i),
            ];
        }

        $bufKey = TelegramMediaGroupInboundBuffer::bufferKey($source->id, $chat->id, '99');
        Cache::put($bufKey, ['fragments' => $fragments, 'updated_at' => microtime(true) - 2], 300);

        $job = new ProcessTelegramMediaGroupJob($source->id, $chat->id, '99');
        $job->handle(
            app(\App\Application\Communication\Action\CreateMessage::class),
            app(\App\Application\Communication\Webhook\InboundAttachmentExtractor::class),
            app(\App\Services\MaybeSendOfflineAutoReply::class),
        );

        $message = MessageModel::where('chat_id', $chat->id)->first();
        $this->assertNotNull($message);
        $payload = $message->payload ?? [];
        $this->assertTrue($payload['inbound_attachments_truncated'] ?? false);
        $this->assertSame(5, $payload['inbound_attachments_total'] ?? null);
        $this->assertSame('[Вложений: 3]', $message->text);

        Queue::assertPushedTimes(DownloadInboundAttachmentJob::class, 3);
    }
}

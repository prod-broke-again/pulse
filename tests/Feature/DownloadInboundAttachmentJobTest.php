<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ChatMessageUpdated;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Jobs\DownloadInboundAttachmentJob;
use App\Support\PendingInboundAttachments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DownloadInboundAttachmentJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_job_removes_pending_and_dispatches_chat_message_updated(): void
    {
        Event::fake([ChatMessageUpdated::class]);

        Http::fake([
            'https://example.com/single.jpg' => Http::response(str_repeat('x', 32), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $source = SourceModel::create([
            'name' => 'TG',
            'type' => 'tg',
            'identifier' => 'tg_bot_job',
            'secret_key' => null,
            'settings' => [],
        ]);
        $department = DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support-job',
            'is_active' => true,
        ]);
        $chat = ChatModel::create([
            'source_id' => $source->id,
            'department_id' => $department->id,
            'external_user_id' => '777001',
            'user_metadata' => [],
            'status' => 'new',
            'assigned_to' => null,
        ]);

        $pending = PendingInboundAttachments::fromDownloadDescriptors([
            [
                'url' => 'https://example.com/single.jpg',
                'file_name' => 'single.jpg',
                'mime_type' => 'image/jpeg',
                'kind' => 'photo',
            ],
        ]);

        $message = MessageModel::create([
            'chat_id' => $chat->id,
            'external_message_id' => 'ext-1',
            'sender_id' => null,
            'sender_type' => 'client',
            'text' => '[Фото]',
            'payload' => [
                'pending_attachments' => $pending,
                'attachments' => [],
            ],
            'is_read' => false,
        ]);

        $job = new DownloadInboundAttachmentJob(
            $message->id,
            'https://example.com/single.jpg',
            'single.jpg',
            'image/jpeg',
            'photo',
        );
        $job->handle();

        $message->refresh();
        $payload = $message->payload ?? [];
        $this->assertSame([], $payload['pending_attachments'] ?? []);
        $this->assertCount(1, is_array($payload['attachments'] ?? null) ? $payload['attachments'] : []);

        Event::assertDispatched(ChatMessageUpdated::class, function (ChatMessageUpdated $e) use ($message): bool {
            return $e->messageId === $message->id
                && $e->chatId === $message->chat_id
                && count($e->attachments) === 1
                && $e->pendingAttachments === [];
        });
    }
}

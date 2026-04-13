<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Models\ModeratorPresence;
use App\Models\User;
use App\Services\MaybeSendOfflineAutoReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class MaybeSendOfflineAutoReplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function createWebSourceWithDepartment(array $settings = []): array
    {
        $source = SourceModel::create([
            'name' => 'Web',
            'type' => 'web',
            'identifier' => 'web_offline_'.uniqid(),
            'secret_key' => null,
            'settings' => array_merge([
                'offline_auto_reply_enabled' => true,
                'offline_auto_reply_text' => 'Мы офлайн, ответим позже.',
            ], $settings),
        ]);
        $department = DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);

        return [$source, $department];
    }

    public function test_sends_system_message_when_no_moderator_online(): void
    {
        [$source, $department] = $this->createWebSourceWithDepartment();

        $chat = ChatModel::create([
            'source_id' => $source->id,
            'department_id' => $department->id,
            'external_user_id' => 'guest_1',
            'user_metadata' => [],
            'status' => 'new',
            'assigned_to' => null,
        ]);

        MessageModel::create([
            'chat_id' => $chat->id,
            'sender_type' => 'client',
            'sender_id' => null,
            'text' => 'Hi',
            'payload' => [],
            'is_read' => false,
        ]);

        $service = app(MaybeSendOfflineAutoReply::class);
        $service->run($chat);

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'sender_type' => 'system',
            'text' => 'Мы офлайн, ответим позже.',
        ]);

        $chat->refresh();
        $this->assertNotNull($chat->last_auto_reply_at);
    }

    public function test_skips_when_moderator_is_online(): void
    {
        [$source, $department] = $this->createWebSourceWithDepartment();

        $mod = User::factory()->create();
        $mod->assignRole('moderator');
        $mod->sources()->sync([$source->id]);

        ModeratorPresence::create([
            'user_id' => $mod->id,
            'manual_online' => true,
            'last_heartbeat_at' => now(),
            'last_activity_at' => now(),
        ]);

        $chat = ChatModel::create([
            'source_id' => $source->id,
            'department_id' => $department->id,
            'external_user_id' => 'guest_2',
            'user_metadata' => [],
            'status' => 'new',
            'assigned_to' => null,
        ]);

        $service = app(MaybeSendOfflineAutoReply::class);
        $service->run($chat);

        $this->assertDatabaseMissing('messages', [
            'chat_id' => $chat->id,
            'sender_type' => 'system',
        ]);
    }

    public function test_skips_second_send_within_cooldown(): void
    {
        [$source, $department] = $this->createWebSourceWithDepartment();

        $chat = ChatModel::create([
            'source_id' => $source->id,
            'department_id' => $department->id,
            'external_user_id' => 'guest_3',
            'user_metadata' => [],
            'status' => 'new',
            'assigned_to' => null,
        ]);

        $service = app(MaybeSendOfflineAutoReply::class);
        $service->run($chat);

        $countAfterFirst = MessageModel::where('chat_id', $chat->id)->where('sender_type', 'system')->count();
        $this->assertSame(1, $countAfterFirst);

        $service->run($chat->fresh());

        $countAfterSecond = MessageModel::where('chat_id', $chat->id)->where('sender_type', 'system')->count();
        $this->assertSame(1, $countAfterSecond);
    }

    public function test_sends_again_after_cooldown_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-13 12:00:00'));

        [$source, $department] = $this->createWebSourceWithDepartment();

        $chat = ChatModel::create([
            'source_id' => $source->id,
            'department_id' => $department->id,
            'external_user_id' => 'guest_4',
            'user_metadata' => [],
            'status' => 'new',
            'assigned_to' => null,
        ]);

        $service = app(MaybeSendOfflineAutoReply::class);
        $service->run($chat);

        $this->assertSame(1, MessageModel::where('chat_id', $chat->id)->where('sender_type', 'system')->count());

        Carbon::setTestNow(Carbon::parse('2026-04-13 12:31:00'));

        $service->run($chat->fresh());

        $this->assertSame(2, MessageModel::where('chat_id', $chat->id)->where('sender_type', 'system')->count());

        Carbon::setTestNow();
    }
}

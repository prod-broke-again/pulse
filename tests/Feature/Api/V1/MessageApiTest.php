<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MessageApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $moderator;

    private SourceModel $source;

    private DepartmentModel $department;

    private ChatModel $chat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->moderator = User::factory()->create();
        $this->moderator->assignRole('moderator');

        $this->source = SourceModel::create([
            'name' => 'Test Source',
            'type' => 'web',
            'identifier' => 'test_web_msg',
            'secret_key' => null,
            'settings' => [],
        ]);

        $this->department = DepartmentModel::create([
            'source_id' => $this->source->id,
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);

        $this->moderator->sources()->sync([$this->source->id]);

        $this->chat = ChatModel::create([
            'source_id' => $this->source->id,
            'department_id' => $this->department->id,
            'external_user_id' => 'ext_user_1',
            'user_metadata' => ['name' => 'Client'],
            'status' => 'active',
            'assigned_to' => $this->moderator->id,
        ]);
    }

    // --- LIST MESSAGES ---

    public function test_list_messages_returns_messages_for_chat(): void
    {
        MessageModel::create([
            'chat_id' => $this->chat->id,
            'sender_type' => 'client',
            'text' => 'Hello from client',
            'payload' => [],
            'is_read' => false,
        ]);
        MessageModel::create([
            'chat_id' => $this->chat->id,
            'sender_id' => $this->moderator->id,
            'sender_type' => 'moderator',
            'text' => 'Hello from moderator',
            'payload' => [],
            'is_read' => true,
        ]);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson("/api/v1/chats/{$this->chat->id}/messages");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'chat_id', 'sender_type', 'text', 'is_read', 'created_at'],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_list_messages_supports_cursor_pagination_with_before_id(): void
    {
        $messages = [];
        for ($i = 0; $i < 5; $i++) {
            $messages[] = MessageModel::create([
                'chat_id' => $this->chat->id,
                'sender_type' => 'client',
                'text' => "Message $i",
                'payload' => [],
                'is_read' => false,
            ]);
        }

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson("/api/v1/chats/{$this->chat->id}/messages?before_id={$messages[3]->id}&limit=2");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        foreach ($data as $msg) {
            $this->assertLessThan($messages[3]->id, $msg['id']);
        }
    }

    public function test_list_messages_respects_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            MessageModel::create([
                'chat_id' => $this->chat->id,
                'sender_type' => 'client',
                'text' => "Message $i",
                'payload' => [],
                'is_read' => false,
            ]);
        }

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson("/api/v1/chats/{$this->chat->id}/messages?limit=3");

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_list_messages_returns_401_without_auth(): void
    {
        $response = $this->getJson("/api/v1/chats/{$this->chat->id}/messages");

        $response->assertUnauthorized();
    }

    // --- SEND MESSAGE ---

    public function test_send_message_creates_new_message(): void
    {
        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/v1/chats/{$this->chat->id}/send", [
                'text' => 'Test reply from moderator',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'chat_id', 'sender_type', 'text', 'created_at'],
            ])
            ->assertJsonPath('data.text', 'Test reply from moderator')
            ->assertJsonPath('data.sender_type', 'moderator')
            ->assertJsonPath('data.chat_id', $this->chat->id);

        $this->assertDatabaseHas('messages', [
            'chat_id' => $this->chat->id,
            'text' => 'Test reply from moderator',
            'sender_type' => 'moderator',
            'sender_id' => $this->moderator->id,
        ]);
    }

    public function test_send_message_with_client_message_id_is_idempotent(): void
    {
        $uuid = Str::uuid()->toString();

        $response1 = $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/v1/chats/{$this->chat->id}/send", [
                'text' => 'Idempotent message',
                'client_message_id' => $uuid,
            ]);

        $response1->assertCreated();
        $messageId = $response1->json('data.id');

        $response2 = $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/v1/chats/{$this->chat->id}/send", [
                'text' => 'Idempotent message',
                'client_message_id' => $uuid,
            ]);

        $response2->assertOk();
        $this->assertEquals($messageId, $response2->json('data.id'));

        $this->assertEquals(1, MessageModel::where('chat_id', $this->chat->id)
            ->where('text', 'Idempotent message')->count());
    }

    public function test_send_message_validation_requires_text_or_attachments(): void
    {
        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/v1/chats/{$this->chat->id}/send", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['text']);
    }

    public function test_send_message_returns_401_without_auth(): void
    {
        $response = $this->postJson("/api/v1/chats/{$this->chat->id}/send", [
            'text' => 'test',
        ]);

        $response->assertUnauthorized();
    }

    public function test_send_message_admin_can_send(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/chats/{$this->chat->id}/send", [
                'text' => 'Admin message',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.text', 'Admin message');
    }
}

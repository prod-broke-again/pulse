<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $moderator;

    private SourceModel $source;

    private DepartmentModel $department;

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
            'identifier' => 'test_web_1',
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
    }

    private function createChat(array $overrides = [], bool $withMessage = true): ChatModel
    {
        $chat = ChatModel::create(array_merge([
            'source_id' => $this->source->id,
            'department_id' => $this->department->id,
            'external_user_id' => 'ext_user_'.uniqid(),
            'user_metadata' => ['name' => 'Test Client'],
            'status' => 'new',
            'assigned_to' => null,
        ], $overrides));

        if ($withMessage) {
            MessageModel::create([
                'chat_id' => $chat->id,
                'sender_id' => null,
                'sender_type' => 'client',
                'text' => 'Test',
                'payload' => null,
                'is_read' => false,
            ]);
        }

        return $chat;
    }

    // --- LIST CHATS ---

    public function test_list_chats_returns_paginated_results(): void
    {
        $this->createChat();
        $this->createChat();
        $this->createChat();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/chats?tab=all');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'source_id', 'department_id', 'status', 'assigned_to', 'created_at'],
                ],
                'meta',
                'links',
            ]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_list_chats_filters_by_status_open(): void
    {
        $this->createChat(['status' => 'new']);
        $this->createChat(['status' => 'active']);
        $this->createChat(['status' => 'closed']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/chats?status=open&tab=all');

        $response->assertOk();

        foreach ($response->json('data') as $chat) {
            $this->assertContains($chat['status'], ['new', 'active']);
        }
    }

    public function test_list_chats_filters_by_status_closed(): void
    {
        $this->createChat(['status' => 'new']);
        $this->createChat(['status' => 'closed']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/chats?status=closed&tab=all');

        $response->assertOk();

        foreach ($response->json('data') as $chat) {
            $this->assertEquals('closed', $chat['status']);
        }
    }

    public function test_list_chats_filters_by_tab_my(): void
    {
        $this->createChat(['assigned_to' => $this->moderator->id, 'status' => 'active']);
        $this->createChat(['assigned_to' => null, 'status' => 'new']);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson('/api/v1/chats?tab=my');

        $response->assertOk();

        foreach ($response->json('data') as $chat) {
            $this->assertEquals($this->moderator->id, $chat['assigned_to']);
        }
    }

    public function test_list_chats_filters_by_tab_unassigned(): void
    {
        $this->createChat(['assigned_to' => $this->moderator->id]);
        $this->createChat(['assigned_to' => null, 'status' => 'new']);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson('/api/v1/chats?tab=unassigned');

        $response->assertOk();

        foreach ($response->json('data') as $chat) {
            $this->assertNull($chat['assigned_to']);
        }
    }

    public function test_list_chats_returns_401_without_auth(): void
    {
        $response = $this->getJson('/api/v1/chats');

        $response->assertUnauthorized();
    }

    // --- ASSIGN ME ---

    public function test_assign_me_sets_moderator_as_assignee(): void
    {
        $chat = $this->createChat(['status' => 'new']);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/v1/chats/{$chat->id}/assign-me");

        $response->assertOk()
            ->assertJsonPath('data.assigned_to', $this->moderator->id);

        $this->assertDatabaseHas('chats', [
            'id' => $chat->id,
            'assigned_to' => $this->moderator->id,
        ]);
    }

    public function test_assign_me_works_for_admin(): void
    {
        $chat = $this->createChat(['status' => 'new']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/chats/{$chat->id}/assign-me");

        $response->assertOk()
            ->assertJsonPath('data.assigned_to', $this->admin->id);
    }

    public function test_assign_me_returns_404_for_non_existent_chat(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/chats/99999/assign-me');

        $response->assertNotFound();
    }

    // --- CLOSE ---

    public function test_close_chat_sets_status_to_closed(): void
    {
        $chat = $this->createChat(['status' => 'active', 'assigned_to' => $this->moderator->id]);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/v1/chats/{$chat->id}/close");

        $response->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('chats', [
            'id' => $chat->id,
            'status' => 'closed',
        ]);
    }

    public function test_close_returns_404_for_non_existent_chat(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/chats/99999/close');

        $response->assertNotFound();
    }

    public function test_list_chats_includes_mobile_fields_and_unread_count(): void
    {
        $chat = $this->createChat(['status' => 'new']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/chats?tab=all');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $chat->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('unread_count', $row);
        $this->assertArrayHasKey('category_code', $row);
        $this->assertArrayHasKey('last_message_preview', $row);
        $this->assertSame(1, $row['unread_count']);
    }

    public function test_tab_counts_returns_my_unassigned_all(): void
    {
        $this->createChat(['status' => 'new', 'assigned_to' => $this->moderator->id]);
        $this->createChat(['status' => 'new', 'assigned_to' => null]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/chats/tab-counts?status=open');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['my', 'unassigned', 'all'],
            ]);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, $data['all']);
    }

    public function test_show_chat_returns_chat_resource(): void
    {
        $chat = $this->createChat(['status' => 'new']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/chats/{$chat->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $chat->id)
            ->assertJsonStructure([
                'data' => ['id', 'unread_count', 'category_code', 'channel'],
            ]);
    }

    public function test_read_chat_updates_cursor_and_marks_client_messages(): void
    {
        $chat = $this->createChat(['status' => 'new'], false);
        $m1 = MessageModel::create([
            'chat_id' => $chat->id,
            'sender_id' => null,
            'sender_type' => 'client',
            'text' => 'A',
            'payload' => null,
            'is_read' => false,
        ]);
        $m2 = MessageModel::create([
            'chat_id' => $chat->id,
            'sender_id' => null,
            'sender_type' => 'client',
            'text' => 'B',
            'payload' => null,
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/v1/chats/{$chat->id}/read", [
                'last_message_id' => $m2->id,
            ]);

        $response->assertOk()->assertJsonPath('data.ok', true);

        $this->assertDatabaseHas('chat_user_read_states', [
            'user_id' => $this->moderator->id,
            'chat_id' => $chat->id,
            'last_read_message_id' => $m2->id,
        ]);

        $this->assertTrue($m1->fresh()->is_read);
        $this->assertTrue($m2->fresh()->is_read);
    }

    // --- DEPARTMENTS LIST ---

    public function test_departments_index_returns_for_source(): void
    {
        $dept2 = DepartmentModel::create([
            'source_id' => $this->source->id,
            'name' => 'Billing',
            'slug' => 'billing',
            'is_active' => true,
        ]);

        $this->moderator->departments()->sync([$this->department->id, $dept2->id]);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson('/api/v1/departments?source_id='.$this->source->id);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->department->id, $ids);
        $this->assertContains($dept2->id, $ids);
    }

    // --- CHANGE DEPARTMENT ---

    public function test_change_department_updates_chat(): void
    {
        $dept2 = DepartmentModel::create([
            'source_id' => $this->source->id,
            'name' => 'Billing',
            'slug' => 'billing-'.uniqid(),
            'is_active' => true,
        ]);

        $this->moderator->departments()->sync([$this->department->id, $dept2->id]);

        $chat = $this->createChat(['department_id' => $this->department->id]);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->patchJson("/api/v1/chats/{$chat->id}/department", [
                'department_id' => $dept2->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.department_id', $dept2->id)
            ->assertJsonPath('data.department.id', $dept2->id);

        $this->assertDatabaseHas('chats', [
            'id' => $chat->id,
            'department_id' => $dept2->id,
        ]);
    }

    public function test_change_department_rejects_foreign_department(): void
    {
        $otherSource = SourceModel::create([
            'name' => 'Other',
            'type' => 'tg',
            'identifier' => 'other_src_'.uniqid(),
            'secret_key' => null,
            'settings' => [],
        ]);

        $foreignDept = DepartmentModel::create([
            'source_id' => $otherSource->id,
            'name' => 'Foreign',
            'slug' => 'foreign-'.uniqid(),
            'is_active' => true,
        ]);

        $this->moderator->departments()->sync([$this->department->id]);

        $chat = $this->createChat(['department_id' => $this->department->id]);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->patchJson("/api/v1/chats/{$chat->id}/department", [
                'department_id' => $foreignDept->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_change_department_after_ai_suggestion_records_reassigned_by_moderator(): void
    {
        $dept2 = DepartmentModel::create([
            'source_id' => $this->source->id,
            'name' => 'Billing',
            'slug' => 'billing-'.uniqid(),
            'is_active' => true,
        ]);

        $this->moderator->departments()->sync([$this->department->id, $dept2->id]);

        $chat = $this->createChat(['department_id' => $this->department->id]);
        $chat->forceFill([
            'ai_suggested_department_id' => $this->department->id,
            'ai_department_confidence' => 0.85,
        ])->save();

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->patchJson("/api/v1/chats/{$chat->id}/department", [
                'department_id' => $dept2->id,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('chats', [
            'id' => $chat->id,
            'department_id' => $dept2->id,
            'department_reassigned_by_user_id' => $this->moderator->id,
        ]);
    }

    public function test_change_department_to_ai_suggested_does_not_set_reassigned_by(): void
    {
        $dept2 = DepartmentModel::create([
            'source_id' => $this->source->id,
            'name' => 'Billing',
            'slug' => 'billing-'.uniqid(),
            'is_active' => true,
        ]);

        $this->moderator->departments()->sync([$this->department->id, $dept2->id]);

        $chat = $this->createChat(['department_id' => $this->department->id]);
        $chat->forceFill([
            'ai_suggested_department_id' => $dept2->id,
            'ai_department_confidence' => 0.9,
        ])->save();

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->patchJson("/api/v1/chats/{$chat->id}/department", [
                'department_id' => $dept2->id,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('chats', [
            'id' => $chat->id,
            'department_id' => $dept2->id,
            'department_reassigned_by_user_id' => null,
        ]);
    }
}

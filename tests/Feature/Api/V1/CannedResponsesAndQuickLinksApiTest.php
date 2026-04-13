<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CannedResponsesAndQuickLinksApiTest extends TestCase
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
            'identifier' => 'test_web_canned',
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
        $this->moderator->departments()->sync([$this->department->id]);
    }

    public function test_admin_can_create_and_list_canned_response(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/canned-responses', [
                'source_id' => $this->source->id,
                'code' => 'hello',
                'title' => 'Приветствие',
                'text' => 'Здравствуйте!',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'hello');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/canned-responses?include_inactive=1')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'hello');
    }

    public function test_moderator_cannot_create_global_canned_response(): void
    {
        $this->actingAs($this->moderator, 'sanctum')
            ->postJson('/api/v1/canned-responses', [
                'source_id' => null,
                'code' => 'global',
                'title' => 'Global',
                'text' => 'x',
            ])
            ->assertForbidden();
    }

    public function test_moderator_can_create_canned_response_for_own_source(): void
    {
        $this->actingAs($this->moderator, 'sanctum')
            ->postJson('/api/v1/canned-responses', [
                'source_id' => $this->source->id,
                'code' => 'mod_tpl',
                'title' => 'Mod',
                'text' => 'Body',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'mod_tpl');
    }

    public function test_admin_can_crud_quick_links_and_reorder(): void
    {
        $r1 = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/quick-links', [
                'source_id' => $this->source->id,
                'title' => 'A',
                'url' => 'https://example.com/a',
                'sort_order' => 10,
            ])
            ->assertCreated()
            ->json('data.id');

        $r2 = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/quick-links', [
                'source_id' => $this->source->id,
                'title' => 'B',
                'url' => 'https://example.com/b',
                'sort_order' => 20,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/quick-links/reorder', [
                'orders' => [
                    ['id' => $r1, 'sort_order' => 100],
                    ['id' => $r2, 'sort_order' => 50],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('quick_links', [
            'id' => $r1,
            'sort_order' => 100,
        ]);
    }

    public function test_analytics_overview_returns_metrics(): void
    {
        $chat = \App\Infrastructure\Persistence\Eloquent\ChatModel::create([
            'source_id' => $this->source->id,
            'department_id' => $this->department->id,
            'external_user_id' => 'u1',
            'user_metadata' => [],
            'status' => 'closed',
            'assigned_to' => null,
        ]);

        MessageModel::create([
            'chat_id' => $chat->id,
            'sender_type' => 'client',
            'text' => 'hi',
            'is_read' => false,
        ]);

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/analytics/overview?from='.$from.'&to='.$to)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'chats_created',
                    'chats_closed',
                    'messages_total',
                    'messages_from_clients',
                    'messages_from_moderators',
                    'messages_from_system',
                ],
            ]);
    }
}

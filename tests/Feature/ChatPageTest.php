<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ChatPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_chat_page_requires_authentication(): void
    {
        $response = $this->get(route('filament.admin.pages.chat-page'));

        $response->assertRedirect();
    }

    public function test_moderator_can_access_chat_page_and_see_chats(): void
    {
        $moderator = User::factory()->create(['name' => 'Mod']);
        $moderator->assignRole('moderator');

        $source = SourceModel::create([
            'name' => 'Test Source',
            'type' => 'web',
            'identifier' => 'web_1',
            'secret_key' => null,
            'settings' => [],
        ]);
        $moderator->sources()->sync([$source->id]);
        $department = DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);
        $chat = ChatModel::create([
            'source_id' => $source->id,
            'department_id' => $department->id,
            'external_user_id' => 'user_1',
            'user_metadata' => [],
            'status' => 'new',
            'assigned_to' => null,
        ]);
        MessageModel::create([
            'chat_id' => $chat->id,
            'external_message_id' => null,
            'sender_id' => null,
            'sender_type' => 'client',
            'text' => 'Hello',
            'payload' => [],
            'is_read' => false,
        ]);

        $response = $this->actingAs($moderator)->get(route('filament.admin.pages.chat-page'));

        $response->assertOk();
        $response->assertSee('Test Source', false);
        $response->assertSee('Chat', false);
    }

    public function test_admin_can_access_chat_page(): void
    {
        $admin = User::factory()->create(['name' => 'Admin']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('filament.admin.pages.chat-page'));

        $response->assertOk();
    }
}

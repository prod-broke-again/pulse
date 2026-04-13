<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Models\ModeratorPresence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WidgetPresenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_session_returns_is_online_false_when_no_moderator_online(): void
    {
        $source = SourceModel::create([
            'name' => 'Web Widget',
            'type' => 'web',
            'identifier' => 'widget_src_'.uniqid(),
            'secret_key' => null,
            'settings' => [],
        ]);
        DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/widget/session', [
            'source_identifier' => $source->identifier,
            'visitor_id' => 'visitor_1',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('is_online', false)
            ->assertJsonPath('chat.is_online', false);
    }

    public function test_session_returns_is_online_true_when_moderator_present(): void
    {
        $source = SourceModel::create([
            'name' => 'Web Widget 2',
            'type' => 'web',
            'identifier' => 'widget_src_'.uniqid(),
            'secret_key' => null,
            'settings' => [],
        ]);
        DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);

        $mod = User::factory()->create();
        $mod->assignRole('moderator');
        $mod->sources()->sync([$source->id]);

        ModeratorPresence::create([
            'user_id' => $mod->id,
            'manual_online' => true,
            'last_heartbeat_at' => now(),
            'last_activity_at' => now(),
        ]);

        $response = $this->postJson('/api/widget/session', [
            'source_identifier' => $source->identifier,
            'visitor_id' => 'visitor_2',
        ]);

        $response->assertOk()
            ->assertJsonPath('is_online', true)
            ->assertJsonPath('chat.is_online', true);
    }
}

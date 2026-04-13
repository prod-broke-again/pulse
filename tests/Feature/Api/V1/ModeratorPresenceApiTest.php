<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\ModeratorPresence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ModeratorPresenceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_me_returns_state_for_moderator(): void
    {
        $user = User::factory()->create();
        $user->assignRole('moderator');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/moderator/presence/me');

        $response->assertOk()
            ->assertJsonPath('data.manual_online', false)
            ->assertJsonPath('data.is_online', false)
            ->assertJsonStructure([
                'data' => [
                    'manual_online',
                    'last_heartbeat_at',
                    'last_activity_at',
                    'is_online',
                    'is_away',
                ],
            ]);
    }

    public function test_toggle_sets_manual_online_and_updates_heartbeat(): void
    {
        $user = User::factory()->create();
        $user->assignRole('moderator');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/moderator/presence/toggle', ['online' => true]);

        $response->assertOk()
            ->assertJsonPath('data.manual_online', true)
            ->assertJsonPath('data.is_online', true);

        $this->assertDatabaseHas('moderator_presences', [
            'user_id' => $user->id,
            'manual_online' => true,
        ]);
    }

    public function test_heartbeat_keeps_online_when_manual_on(): void
    {
        $user = User::factory()->create();
        $user->assignRole('moderator');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/moderator/presence/toggle', ['online' => true]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/moderator/presence/heartbeat');

        $response->assertOk()->assertJsonPath('data.is_online', true);
    }

    public function test_activity_endpoint_updates_presence(): void
    {
        $user = User::factory()->create();
        $user->assignRole('moderator');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/moderator/presence/toggle', ['online' => true]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/moderator/presence/activity');

        $response->assertOk();
        $p = ModeratorPresence::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($p?->last_activity_at);
    }

    public function test_guest_cannot_access_presence_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/moderator/presence/me')
            ->assertForbidden();
    }
}

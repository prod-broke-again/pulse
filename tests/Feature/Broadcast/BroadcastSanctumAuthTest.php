<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcast;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BroadcastSanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_broadcasting_auth_accepts_bearer_sanctum_token_for_private_moderator_channel(): void
    {
        $user = User::factory()->create();
        $user->assignRole('moderator');

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-moderator.'.$user->id,
            ]);

        $response->assertOk();
    }

    public function test_broadcasting_auth_returns_401_without_token(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-moderator.1',
        ]);

        $response->assertUnauthorized();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->moderator = User::factory()->create();
        $this->moderator->assignRole('moderator');
    }

    // --- REGISTER TOKEN ---

    public function test_register_device_token_creates_record(): void
    {
        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson('/api/v1/devices/register-token', [
                'token' => 'fcm_test_token_12345',
                'platform' => 'android',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'token', 'platform'],
            ])
            ->assertJsonPath('data.token', 'fcm_test_token_12345')
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $this->moderator->id,
            'token' => 'fcm_test_token_12345',
            'platform' => 'android',
        ]);
    }

    public function test_register_device_token_updates_existing(): void
    {
        $otherUser = User::factory()->create();

        DeviceToken::create([
            'user_id' => $otherUser->id,
            'token' => 'fcm_existing_token',
            'platform' => 'ios',
        ]);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson('/api/v1/devices/register-token', [
                'token' => 'fcm_existing_token',
                'platform' => 'desktop',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.platform', 'desktop');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $this->moderator->id,
            'token' => 'fcm_existing_token',
            'platform' => 'desktop',
        ]);

        $this->assertEquals(1, DeviceToken::where('token', 'fcm_existing_token')->count());
    }

    public function test_register_device_validates_platform(): void
    {
        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson('/api/v1/devices/register-token', [
                'token' => 'test_token',
                'platform' => 'unknown_platform',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_register_device_requires_token(): void
    {
        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson('/api/v1/devices/register-token', [
                'platform' => 'ios',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_register_device_returns_401_without_auth(): void
    {
        $response = $this->postJson('/api/v1/devices/register-token', [
            'token' => 'test',
            'platform' => 'android',
        ]);

        $response->assertUnauthorized();
    }

    // --- DESTROY TOKEN ---

    public function test_destroy_device_token_deletes_own_token(): void
    {
        DeviceToken::create([
            'user_id' => $this->moderator->id,
            'token' => 'token_to_delete',
            'platform' => 'android',
        ]);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->deleteJson('/api/v1/devices/token_to_delete');

        $response->assertOk()
            ->assertJsonPath('data.message', 'Device token removed.');

        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'token_to_delete',
        ]);
    }

    public function test_destroy_does_not_delete_other_users_token(): void
    {
        $otherUser = User::factory()->create();

        DeviceToken::create([
            'user_id' => $otherUser->id,
            'token' => 'other_user_token',
            'platform' => 'ios',
        ]);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->deleteJson('/api/v1/devices/other_user_token');

        $response->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'token' => 'other_user_token',
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_destroy_returns_401_without_auth(): void
    {
        $response = $this->deleteJson('/api/v1/devices/some_token');

        $response->assertUnauthorized();
    }
}

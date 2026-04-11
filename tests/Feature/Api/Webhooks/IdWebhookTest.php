<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Webhooks;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class IdWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        config([
            'pulse.id_webhooks.enabled' => true,
            'pulse.id_webhooks.secret' => 'test-secret',
            'pulse.id_webhooks.replay_tolerance_seconds' => 300,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function signedServerVars(string $rawBody): array
    {
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts.'.'.$rawBody, 'test-secret');

        return [
            'HTTP_X_PULSE_TIMESTAMP' => $ts,
            'HTTP_X_PULSE_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];
    }

    public function test_user_revoked_clears_sanctum_tokens(): void
    {
        $uuid = 'a0000000-0000-4000-8000-000000000099';
        $user = User::factory()->create(['id_user_uuid' => $uuid]);
        $user->assignRole('moderator');
        $user->createToken('device')->plainTextToken;

        $this->assertSame(1, DB::table('personal_access_tokens')->count());

        $body = json_encode(['id_user_uuid' => $uuid], JSON_THROW_ON_ERROR);
        $server = $this->signedServerVars($body);

        $response = $this->call('POST', '/api/webhooks/id/user-revoked', [], [], [], $server, $body);

        $response->assertOk();
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_user_updated_updates_profile_fields(): void
    {
        $uuid = 'b0000000-0000-4000-8000-000000000099';
        $user = User::factory()->create([
            'id_user_uuid' => $uuid,
            'email' => 'old@example.com',
            'name' => 'Old',
        ]);
        $user->assignRole('moderator');

        $body = json_encode([
            'id_user_uuid' => $uuid,
            'name' => 'New Name',
            'email' => 'new@example.com',
            'avatar_url' => 'https://example.com/a.png',
        ], JSON_THROW_ON_ERROR);
        $server = $this->signedServerVars($body);

        $response = $this->call('POST', '/api/webhooks/id/user-updated', [], [], [], $server, $body);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'id_user_uuid' => $uuid,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }
}

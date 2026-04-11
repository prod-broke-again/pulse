<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SsoExchangeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        config([
            'pulse.id.id_url_public' => 'http://id.test',
            'pulse.id.id_url_internal' => 'http://id.test',
            'pulse.id.timeout_seconds' => 5,
            'pulse.id.profile_path' => '/api/v1/user',
            'pulse.id.oauth_client_id' => '00000000-0000-4000-8000-000000000001',
        ]);
    }

    public function test_sso_exchange_returns_sanctum_token_for_valid_id_token(): void
    {
        $uuid = (string) Str::uuid();

        Http::fake([
            'http://id.test/api/v1/user' => Http::response([
                'data' => [
                    'id' => $uuid,
                    'name' => 'SSO User',
                    'email' => 'sso@example.com',
                    'avatar_sm' => null,
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'email' => 'sso@example.com',
            'id_user_uuid' => null,
        ]);
        $user->assignRole('moderator');

        $response = $this->postJson('/api/v1/auth/sso/exchange', [
            'access_token' => 'fake-id-access-token',
            'device_name' => 'test-device',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'sso@example.com')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'roles', 'source_ids', 'department_ids'],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'id_user_uuid' => $uuid,
        ]);
    }

    public function test_sso_exchange_returns_401_when_id_returns_401(): void
    {
        Http::fake([
            'http://id.test/api/v1/user' => Http::response(['message' => 'Unauthenticated.'], 401),
        ]);

        $response = $this->postJson('/api/v1/auth/sso/exchange', [
            'access_token' => 'bad-token',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('code', 'SSO_ID_PROFILE_UNAUTHORIZED');
    }

    public function test_sso_exchange_returns_403_when_user_has_no_staff_role(): void
    {
        $uuid = (string) Str::uuid();

        Http::fake([
            'http://id.test/api/v1/user' => Http::response([
                'data' => [
                    'id' => $uuid,
                    'name' => 'No Role',
                    'email' => 'norole@example.com',
                ],
            ], 200),
        ]);

        User::factory()->create([
            'email' => 'norole@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/sso/exchange', [
            'access_token' => 'valid-looking-token',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_sso_exchange_with_authorization_code_exchanges_at_idp_then_returns_sanctum_token(): void
    {
        $uuid = (string) Str::uuid();

        Http::fake(function ($request) use ($uuid) {
            $url = $request->url();
            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'exchanged-access-token'], 200);
            }
            if (str_contains($url, '/api/v1/user')) {
                return Http::response([
                    'data' => [
                        'id' => $uuid,
                        'name' => 'Code Flow',
                        'email' => 'codeflow@example.com',
                        'avatar_sm' => null,
                    ],
                ], 200);
            }

            return Http::response(['message' => 'unexpected'], 500);
        });

        $user = User::factory()->create([
            'email' => 'codeflow@example.com',
            'id_user_uuid' => null,
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/v1/auth/sso/exchange', [
            'code' => 'auth-code-from-idp',
            'code_verifier' => 'verifier',
            'redirect_uri' => 'http://localhost:5174/auth/callback',
            'state' => 'opaque-state',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'codeflow@example.com');

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_sso_exchange_returns_sso_id_invalid_grant_when_idp_returns_invalid_grant(): void
    {
        Http::fake([
            'http://id.test/oauth/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'The authorization code is invalid or expired.',
                'hint' => 'Authorization code has been revoked',
            ], 400),
        ]);

        $response = $this->postJson('/api/v1/auth/sso/exchange', [
            'code' => 'already-used-code',
            'code_verifier' => 'verifier',
            'redirect_uri' => 'http://localhost:5174/auth/callback',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('code', 'SSO_ID_INVALID_GRANT')
            ->assertJsonPath('idp.oauth_error', 'invalid_grant')
            ->assertJsonFragment(['hint' => 'Authorization code has been revoked']);
    }
}

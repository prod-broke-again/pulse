<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function createAdmin(array $overrides = []): User
    {
        $user = User::factory()->create($overrides);
        $user->assignRole('admin');

        return $user;
    }

    private function createModerator(array $overrides = []): User
    {
        $user = User::factory()->create($overrides);
        $user->assignRole('moderator');

        return $user;
    }

    // --- LOGIN ---

    public function test_login_returns_token_for_admin(): void
    {
        $user = $this->createAdmin(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'avatar_url', 'roles', 'source_ids', 'department_ids'],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertEquals($user->id, $response->json('data.user.id'));
    }

    public function test_login_returns_token_for_moderator(): void
    {
        $user = $this->createModerator(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertContains('moderator', $response->json('data.user.roles'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = $this->createAdmin(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_for_non_existent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_denied_for_user_without_role(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertForbidden()
            ->assertJsonFragment(['code' => 'FORBIDDEN']);
    }

    public function test_login_validation_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_accepts_custom_device_name(): void
    {
        $user = $this->createAdmin(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'device_name' => 'My Desktop App',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.token'));
    }

    // --- ME ---

    public function test_me_returns_authenticated_user(): void
    {
        $user = $this->createAdmin();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'avatar_url', 'roles', 'source_ids', 'department_ids'],
            ])
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_me_returns_401_without_token(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    // --- LOGOUT ---

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->createAdmin();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('data.message', 'Logged out successfully.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_returns_401_without_token(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }

    // --- PROFILE AVATAR ---

    public function test_upload_avatar_stores_file_and_returns_user_with_avatar_url(): void
    {
        $user = $this->createModerator();
        $token = $user->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->image('avatar.png', 120, 120);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/v1/auth/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'avatar_url', 'roles', 'source_ids', 'department_ids'],
            ]);

        $avatarUrl = $response->json('data.avatar_url');
        $this->assertIsString($avatarUrl);
        $this->assertNotSame('', $avatarUrl);
        $this->assertStringContainsString('/avatars/'.$user->id.'/', $avatarUrl);

        $user->refresh();
        $this->assertSame($avatarUrl, $user->avatar_url);

        $path = parse_url($avatarUrl, PHP_URL_PATH);
        $this->assertIsString($path);
        $fullPath = public_path(ltrim(str_replace('\\', '/', $path), '/'));
        $this->assertFileExists($fullPath);

        @unlink($fullPath);
        @rmdir(dirname($fullPath));
    }

    public function test_upload_avatar_rejects_non_image(): void
    {
        $user = $this->createModerator();
        $token = $user->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/v1/auth/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_upload_avatar_returns_401_without_token(): void
    {
        $file = UploadedFile::fake()->image('avatar.png', 50, 50);

        $response = $this->post('/api/v1/auth/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertUnauthorized();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class UploadApiTest extends TestCase
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

    public function test_upload_file_stores_and_returns_path(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('screenshot.png', 200, 200);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson('/api/v1/uploads', [
                'file' => $file,
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['path', 'original_name', 'mime_type', 'size'],
            ])
            ->assertJsonPath('data.original_name', 'screenshot.png');

        $path = $response->json('data.path');
        $this->assertNotEmpty($path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_upload_accepts_pdf(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson('/api/v1/uploads', [
                'file' => $file,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.original_name', 'document.pdf');
    }

    public function test_upload_requires_file(): void
    {
        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson('/api/v1/uploads', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_returns_401_without_auth(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->postJson('/api/v1/uploads', [
            'file' => $file,
        ]);

        $response->assertUnauthorized();
    }
}

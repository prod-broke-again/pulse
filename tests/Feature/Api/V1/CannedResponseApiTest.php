<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Models\CannedResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CannedResponseApiTest extends TestCase
{
    use RefreshDatabase;

    private User $moderator;

    private SourceModel $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->moderator = User::factory()->create();
        $this->moderator->assignRole('moderator');

        $this->source = SourceModel::create([
            'name' => 'Test Source',
            'type' => 'web',
            'identifier' => 'test_canned',
            'secret_key' => null,
            'settings' => [],
        ]);
    }

    public function test_list_canned_responses_returns_active_only(): void
    {
        CannedResponse::create([
            'source_id' => $this->source->id,
            'code' => 'greet',
            'title' => 'Greeting',
            'text' => 'Hello! How can I help you?',
            'is_active' => true,
        ]);
        CannedResponse::create([
            'source_id' => $this->source->id,
            'code' => 'inactive',
            'title' => 'Inactive template',
            'text' => 'Should not appear',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson('/api/v1/canned-responses');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'source_id', 'code', 'title', 'text', 'is_active'],
                ],
            ]);

        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('greet', $codes);
        $this->assertNotContains('inactive', $codes);
    }

    public function test_list_canned_responses_filters_by_source_id(): void
    {
        $otherSource = SourceModel::create([
            'name' => 'Other',
            'type' => 'tg',
            'identifier' => 'other_src',
            'secret_key' => null,
            'settings' => [],
        ]);

        CannedResponse::create([
            'source_id' => $this->source->id,
            'code' => 'src1',
            'title' => 'Source 1 template',
            'text' => 'For source 1',
            'is_active' => true,
        ]);
        CannedResponse::create([
            'source_id' => $otherSource->id,
            'code' => 'src2',
            'title' => 'Source 2 template',
            'text' => 'For source 2',
            'is_active' => true,
        ]);
        CannedResponse::create([
            'source_id' => null,
            'code' => 'global',
            'title' => 'Global template',
            'text' => 'For everyone',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson("/api/v1/canned-responses?source_id={$this->source->id}");

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('src1', $codes);
        $this->assertContains('global', $codes);
        $this->assertNotContains('src2', $codes);
    }

    public function test_list_canned_responses_filters_by_search_query(): void
    {
        CannedResponse::create([
            'source_id' => null,
            'code' => 'delivery',
            'title' => 'Delivery info',
            'text' => 'Your order will be delivered in 3-5 days.',
            'is_active' => true,
        ]);
        CannedResponse::create([
            'source_id' => null,
            'code' => 'refund',
            'title' => 'Refund policy',
            'text' => 'We can issue a refund within 14 days.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson('/api/v1/canned-responses?q=delivery');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('delivery', $codes);
        $this->assertNotContains('refund', $codes);
    }

    public function test_list_canned_responses_returns_empty_when_no_matches(): void
    {
        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson('/api/v1/canned-responses?q=nonexistent_xyz');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    public function test_list_canned_responses_returns_401_without_auth(): void
    {
        $response = $this->getJson('/api/v1/canned-responses');

        $response->assertUnauthorized();
    }
}

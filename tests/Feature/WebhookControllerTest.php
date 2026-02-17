<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Jobs\ProcessIncomingMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_vk_webhook_dispatches_job_and_returns_200(): void
    {
        Queue::fake();

        $source = SourceModel::create([
            'name' => 'VK Test',
            'type' => 'vk',
            'identifier' => 'vk_test_1',
            'secret_key' => null,
            'settings' => [],
        ]);
        DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);

        $payload = [
            'user_id' => '12345',
            'body' => 'Hello',
            'message_id' => 999,
        ];

        $response = $this->postJson(route('webhook.vk', ['sourceId' => $source->id]), $payload);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        Queue::assertPushed(ProcessIncomingMessageJob::class, function (ProcessIncomingMessageJob $job) use ($source, $payload) {
            return $job->sourceId === $source->id && $job->payload === $payload;
        });
    }

    public function test_telegram_webhook_dispatches_job_and_returns_200(): void
    {
        Queue::fake();

        $source = SourceModel::create([
            'name' => 'TG Test',
            'type' => 'tg',
            'identifier' => 'tg_test_1',
            'secret_key' => null,
            'settings' => [],
        ]);
        DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);

        $payload = [
            'update_id' => 1,
            'message' => [
                'message_id' => 42,
                'from' => ['id' => 551199, 'first_name' => 'Test'],
                'text' => 'Hi',
            ],
        ];

        $response = $this->postJson(route('webhook.telegram', ['sourceId' => $source->id]), $payload);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        Queue::assertPushed(ProcessIncomingMessageJob::class, function (ProcessIncomingMessageJob $job) use ($source, $payload) {
            return $job->sourceId === $source->id && $job->payload === $payload;
        });
    }

    public function test_webhook_accepts_payload_without_validating_content(): void
    {
        Queue::fake();

        $source = SourceModel::create([
            'name' => 'VK',
            'type' => 'vk',
            'identifier' => 'vk_any',
            'secret_key' => null,
            'settings' => [],
        ]);
        DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'D',
            'slug' => 'd',
            'is_active' => true,
        ]);

        $response = $this->postJson(route('webhook.vk', ['sourceId' => $source->id]), [
            'type' => 'message_new',
            'object' => ['user_id' => 1, 'body' => 'x', 'message_id' => 1],
            'user_id' => '1',
            'body' => 'x',
        ]);

        $response->assertOk();
        Queue::assertPushed(ProcessIncomingMessageJob::class);
    }
}

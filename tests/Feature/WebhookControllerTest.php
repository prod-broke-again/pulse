<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Jobs\ProcessIncomingMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('pulse.vk.bot_token', '');
        Config::set('pulse.vk.callback_secret', '');
        Config::set('pulse.vk.callback_confirmation', '');

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

    public function test_vk_callback_confirmation_returns_plain_text_and_skips_queue(): void
    {
        Queue::fake();

        $source = SourceModel::create([
            'name' => 'VK Callback',
            'type' => 'vk',
            'identifier' => '236785354',
            'secret_key' => null,
            'settings' => ['vk_callback_confirmation' => '96de43c5'],
        ]);
        DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);

        $response = $this->postJson(route('webhook.vk', ['sourceId' => $source->id]), [
            'type' => 'confirmation',
            'group_id' => 236785354,
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame('96de43c5', $response->getContent());
        Queue::assertNothingPushed();
    }

    public function test_vk_callback_confirmation_checks_secret_when_configured(): void
    {
        Queue::fake();

        $source = SourceModel::create([
            'name' => 'VK Secret',
            'type' => 'vk',
            'identifier' => '236785354',
            'secret_key' => 'aaQ13axAPQEcczQa',
            'settings' => ['vk_callback_confirmation' => '96de43c5'],
        ]);
        DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);

        $wrongSecret = $this->postJson(route('webhook.vk', ['sourceId' => $source->id]), [
            'type' => 'confirmation',
            'group_id' => 236785354,
            'secret' => 'wrong',
        ]);
        $wrongSecret->assertForbidden();

        $ok = $this->postJson(route('webhook.vk', ['sourceId' => $source->id]), [
            'type' => 'confirmation',
            'group_id' => 236785354,
            'secret' => 'aaQ13axAPQEcczQa',
        ]);
        $ok->assertOk();
        $this->assertSame('96de43c5', $ok->getContent());
        Queue::assertNothingPushed();
    }

    public function test_vk_callback_confirmation_without_code_returns_503_plain(): void
    {
        Queue::fake();

        $source = SourceModel::create([
            'name' => 'VK No Code',
            'type' => 'vk',
            'identifier' => '236785354',
            'secret_key' => null,
            'settings' => [],
        ]);
        DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);

        $response = $this->postJson(route('webhook.vk', ['sourceId' => $source->id]), [
            'type' => 'confirmation',
            'group_id' => 236785354,
        ]);

        $response->assertStatus(503);
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('VK callback confirmation is not set', $response->getContent());
        Queue::assertNothingPushed();
    }

    public function test_vk_callback_confirmation_falls_back_to_pulse_config(): void
    {
        Queue::fake();
        Config::set('pulse.vk.callback_confirmation', 'env-confirm-1');

        $source = SourceModel::create([
            'name' => 'VK Env Confirm',
            'type' => 'vk',
            'identifier' => '236785354',
            'secret_key' => null,
            'settings' => [],
        ]);
        DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);

        $response = $this->postJson(route('webhook.vk', ['sourceId' => $source->id]), [
            'type' => 'confirmation',
            'group_id' => 236785354,
        ]);

        $response->assertOk();
        $this->assertSame('env-confirm-1', $response->getContent());
        Queue::assertNothingPushed();
    }
}

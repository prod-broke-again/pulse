<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Communication\Action\ProcessInboundWebhook;
use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BusinessConnectionEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_business_connection_enabled_saves_id_to_source_settings(): void
    {
        $source = $this->createTgSource();
        $messenger = $this->mockMessenger();

        app(ProcessInboundWebhook::class)->run($source->id, $messenger, [
            'business_connection' => [
                'id' => 'conn-abc-123',
                'user' => ['id' => 555],
                'is_enabled' => true,
            ],
        ]);

        $source->refresh();
        $this->assertSame('conn-abc-123', $source->settings['business_connection_id'] ?? null);
        $this->assertSame('555', $source->settings['business_connection_user_id'] ?? null);
        $this->assertNotEmpty($source->settings['business_connection_activated_at'] ?? null);
    }

    public function test_business_connection_disabled_clears_id(): void
    {
        $source = $this->createTgSource();
        $source->update([
            'settings' => array_merge($source->settings ?? [], [
                'business_connection_id' => 'conn-abc-123',
                'business_connection_user_id' => '555',
                'business_connection_activated_at' => '2026-01-01T00:00:00+00:00',
            ]),
        ]);
        $messenger = $this->mockMessenger();

        app(ProcessInboundWebhook::class)->run($source->id, $messenger, [
            'business_connection' => [
                'id' => 'conn-abc-123',
                'is_enabled' => false,
            ],
        ]);

        $source->refresh();
        $this->assertArrayNotHasKey('business_connection_id', $source->settings ?? []);
        $this->assertArrayNotHasKey('business_connection_user_id', $source->settings ?? []);
        $this->assertArrayNotHasKey('business_connection_activated_at', $source->settings ?? []);
    }

    public function test_business_connection_disabled_with_different_id_is_ignored(): void
    {
        $source = $this->createTgSource();
        $source->update([
            'settings' => array_merge($source->settings ?? [], [
                'business_connection_id' => 'current-active',
            ]),
        ]);
        $messenger = $this->mockMessenger();

        app(ProcessInboundWebhook::class)->run($source->id, $messenger, [
            'business_connection' => [
                'id' => 'old-other-id',
                'is_enabled' => false,
            ],
        ]);

        $source->refresh();
        $this->assertSame('current-active', $source->settings['business_connection_id'] ?? null);
    }

    private function createTgSource(): SourceModel
    {
        $source = SourceModel::query()->create([
            'name' => 'TG business',
            'type' => 'tg',
            'identifier' => 'tg_biz_'.uniqid(),
            'secret_key' => null,
            'settings' => ['telegram_mode' => 'business'],
        ]);
        DepartmentModel::query()->create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support_'.uniqid(),
            'is_active' => true,
        ]);

        return $source;
    }

    private function mockMessenger(): MessengerProviderInterface
    {
        $mock = $this->createMock(MessengerProviderInterface::class);
        $mock->method('validateWebhook')->willReturn(true);

        return $mock;
    }
}

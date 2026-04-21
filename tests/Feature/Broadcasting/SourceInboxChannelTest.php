<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

final class SourceInboxChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    /**
     * Resolve the registered `source-inbox.{sourceId}` callback (phpunit uses `null` broadcaster
     * so HTTP /broadcasting/auth is a no-op; we assert the same closures directly).
     *
     * @return callable(mixed, int): mixed
     */
    private function sourceInboxChannelAuth(): callable
    {
        $broadcaster = app(BroadcastManager::class)->driver();
        $ref = new ReflectionClass($broadcaster);
        $prop = $ref->getProperty('channels');
        $prop->setAccessible(true);
        /** @var array<string, callable> $channels */
        $channels = $prop->getValue($broadcaster);
        $cb = $channels['source-inbox.{sourceId}'] ?? null;
        $this->assertIsCallable($cb, 'source-inbox channel must be registered.');

        return $cb;
    }

    public function test_admin_can_authorize_source_inbox_channel_for_any_source(): void
    {
        $auth = $this->sourceInboxChannelAuth();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $source = SourceModel::query()->create([
            'name' => 'Test',
            'type' => 'web',
            'identifier' => 'test-admin-source-inbox-'.uniqid('', true),
        ]);

        $otherSource = SourceModel::query()->create([
            'name' => 'Other',
            'type' => 'web',
            'identifier' => 'test-admin-other-'.uniqid('', true),
        ]);

        $this->assertTrue($auth($admin, $source->id));
        $this->assertTrue($auth($admin, $otherSource->id));
    }

    public function test_moderator_can_authorize_only_assigned_source(): void
    {
        $auth = $this->sourceInboxChannelAuth();

        $sourceX = SourceModel::query()->create([
            'name' => 'X',
            'type' => 'web',
            'identifier' => 'mod-source-x-'.uniqid('', true),
        ]);
        $sourceY = SourceModel::query()->create([
            'name' => 'Y',
            'type' => 'web',
            'identifier' => 'mod-source-y-'.uniqid('', true),
        ]);

        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        $moderator->sources()->attach($sourceX->id);

        $this->assertTrue($auth($moderator, $sourceX->id));
        $this->assertFalse($auth($moderator, $sourceY->id));
    }

    public function test_user_without_mod_or_admin_role_cannot_authorize_source_inbox(): void
    {
        $auth = $this->sourceInboxChannelAuth();

        $source = SourceModel::query()->create([
            'name' => 'Z',
            'type' => 'web',
            'identifier' => 'plain-source-'.uniqid('', true),
        ]);

        $user = User::factory()->create();

        $this->assertFalse($auth($user, $source->id));
    }
}

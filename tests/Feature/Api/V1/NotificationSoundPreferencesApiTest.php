<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class NotificationSoundPreferencesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('moderator', 'web');
    }

    public function test_moderator_can_get_and_patch_notification_sound_preferences(): void
    {
        $user = User::factory()->create();
        $user->assignRole('moderator');

        Sanctum::actingAs($user);

        $show = $this->getJson('/api/v1/user/notification-sound-preferences');
        $show->assertOk();
        $show->assertJsonPath('data.notification_sound_prefs.mute', false);

        $patch = $this->patchJson('/api/v1/user/notification-sound-preferences', [
            'mute' => true,
            'volume' => 0.5,
            'presets' => [
                'in_app' => 'notification_simple_02',
            ],
        ]);
        $patch->assertOk();
        $patch->assertJsonPath('data.notification_sound_prefs.mute', true);
        $patch->assertJsonPath('data.notification_sound_prefs.volume', 0.5);
        $patch->assertJsonPath('data.notification_sound_prefs.presets.in_app', 'notification_simple_02');

        $user->refresh();
        $this->assertIsArray($user->notification_sound_prefs);
        $this->assertTrue($user->notification_sound_prefs['mute']);
    }
}

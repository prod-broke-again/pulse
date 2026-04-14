<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\NotificationSoundPreferencesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class NotificationSoundPreferencesController extends Controller
{
    public function __construct(
        private readonly NotificationSoundPreferencesService $prefs,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureStaff($user);

        return response()->json([
            'data' => [
                'notification_sound_prefs' => $this->prefs->forUser($user),
                'available_presets' => array_map(
                    fn (array $p) => ['label' => $p['label'] ?? ''],
                    config('notification_sounds.presets', [])
                ),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureStaff($user);

        $current = $this->prefs->forUser($user);
        $incoming = $request->all();
        if (isset($incoming['presets']) && is_array($incoming['presets'])) {
            $incoming['presets'] = array_merge($current['presets'], $incoming['presets']);
        }
        $normalized = $this->prefs->validateAndNormalize(array_merge($current, $incoming));
        $user->forceFill(['notification_sound_prefs' => $normalized])->save();

        return response()->json([
            'data' => [
                'notification_sound_prefs' => $this->prefs->forUser($user->fresh() ?? $user),
                'user' => new UserResource($user->fresh() ?? $user),
            ],
        ]);
    }

    private function ensureStaff(User $user): void
    {
        if (! $user->hasAnyRole(['admin', 'moderator'])) {
            abort(403);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'roles' => $user->getRoleNames()->values(),
            'is_admin' => $user->hasRole('admin'),
            'source_ids' => $this->pulseSourceIds($user),
            'department_ids' => $user->departments()->pluck('departments.id')->values(),
            'notification_sound_prefs' => app(\App\Services\NotificationSoundPreferencesService::class)->forUser($user),
        ];
    }

    /**
     * Админ подписывается на все источники (как в routes/channels.php для source-inbox), без pivot source_user.
     *
     * @return list<int>
     */
    private function pulseSourceIds(User $user): array
    {
        if ($user->hasRole('admin')) {
            return SourceModel::query()
                ->orderBy('id')
                ->pluck('id')
                ->map(fn (mixed $id) => (int) $id)
                ->values()
                ->all();
        }

        return $user->sources()
            ->pluck('sources.id')
            ->map(fn (mixed $id) => (int) $id)
            ->values()
            ->all();
    }
}

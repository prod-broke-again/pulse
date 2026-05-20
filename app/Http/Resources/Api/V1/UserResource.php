<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Models\User;
use App\Services\InboxFilterPreferencesService;
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
            'sources' => $this->pulseSources($user),
            'department_ids' => $user->departments()->pluck('departments.id')->values(),
            'departments' => $this->pulseDepartments($user),
            'notification_sound_prefs' => app(\App\Services\NotificationSoundPreferencesService::class)->forUser($user),
            'inbox_filter_prefs' => app(InboxFilterPreferencesService::class)->forUser($user),
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

    /**
     * @return list<array{id:int,name:string,type:string}>
     */
    private function pulseSources(User $user): array
    {
        $query = SourceModel::query()
            ->select(['id', 'name', 'type'])
            ->orderBy('id');

        if (! $user->hasRole('admin')) {
            $query->whereIn('id', $user->sources()->pluck('sources.id')->all());
        }

        return $query
            ->get()
            ->map(static fn (SourceModel $source): array => [
                'id' => (int) $source->id,
                'name' => (string) $source->name,
                'type' => (string) $source->type,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,source_id:int,name:string,slug:string,category:string,icon:string|null,ai_enabled:bool,is_active:bool}>
     */
    private function pulseDepartments(User $user): array
    {
        $query = \App\Infrastructure\Persistence\Eloquent\DepartmentModel::query()
            ->select(['id', 'source_id', 'name', 'slug', 'category', 'icon', 'ai_enabled', 'is_active'])
            ->where('is_active', true)
            ->orderBy('id');

        if (! $user->hasRole('admin')) {
            $pivotDeptIds = $user->departments()->pluck('departments.id')->all();
            if ($pivotDeptIds !== []) {
                $query->whereIn('id', $pivotDeptIds);
            } else {
                $sourceIds = $user->sources()->pluck('sources.id')->all();
                $query->whereIn('source_id', $sourceIds);
            }
        }

        return $query
            ->get()
            ->map(static fn (\App\Infrastructure\Persistence\Eloquent\DepartmentModel $dept): array => [
                'id' => (int) $dept->id,
                'source_id' => (int) $dept->source_id,
                'name' => (string) $dept->name,
                'slug' => (string) $dept->slug,
                'category' => $dept->category instanceof \BackedEnum ? $dept->category->value : (string) $dept->category,
                'icon' => $dept->icon,
                'ai_enabled' => (bool) $dept->ai_enabled,
                'is_active' => (bool) $dept->is_active,
            ])
            ->values()
            ->all();
    }
}

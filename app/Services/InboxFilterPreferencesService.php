<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class InboxFilterPreferencesService
{
    private const CHANNEL_TYPES = ['tg', 'vk', 'web', 'max'];

    /**
     * @return array{
     *     enabled_source_ids: list<int>|null,
     *     enabled_channel_types: list<string>|null,
     *     enabled_department_ids: list<int>|null,
     * }
     */
    public function forUser(User $user): array
    {
        $stored = $user->inbox_filter_prefs;
        if (! is_array($stored)) {
            $stored = [];
        }

        return $this->normalizeAfterValidation($user, $stored);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     enabled_source_ids: list<int>|null,
     *     enabled_channel_types: list<string>|null,
     *     enabled_department_ids: list<int>|null,
     * }
     */
    public function validateAndNormalize(User $user, array $input): array
    {
        Validator::make($input, [
            'enabled_source_ids' => ['sometimes', 'nullable', 'array'],
            'enabled_source_ids.*' => ['integer'],
            'enabled_channel_types' => ['sometimes', 'nullable', 'array'],
            'enabled_channel_types.*' => ['string', Rule::in(self::CHANNEL_TYPES)],
            'enabled_department_ids' => ['sometimes', 'nullable', 'array'],
            'enabled_department_ids.*' => ['integer'],
        ])->validate();

        return $this->normalizeAfterValidation($user, $input);
    }

    /**
     * Intersect requested ids with what the user may select.
     *
     * @param  array<string, mixed>  $stored
     * @return array{
     *     enabled_source_ids: list<int>|null,
     *     enabled_channel_types: list<string>|null,
     *     enabled_department_ids: list<int>|null,
     * }
     */
    private function normalizeAfterValidation(User $user, array $stored): array
    {
        $allowedSources = $this->allowedSourceIds($user);
        $sourceIds = $stored['enabled_source_ids'] ?? null;
        if (is_array($sourceIds) && $sourceIds !== []) {
            $sourceIds = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $sourceIds)));
            $sourceIds = array_values(array_intersect($sourceIds, $allowedSources));
            if ($sourceIds === []) {
                $sourceIds = null;
            }
        } else {
            $sourceIds = null;
        }

        $channels = $stored['enabled_channel_types'] ?? null;
        if (is_array($channels) && $channels !== []) {
            $channels = array_values(array_unique(array_map(static fn ($c) => (string) $c, $channels)));
            $channels = array_values(array_intersect($channels, self::CHANNEL_TYPES));
            if ($channels === []) {
                $channels = null;
            }
        } else {
            $channels = null;
        }

        $allowedDepts = $this->allowedDepartmentIds($user);
        $deptIds = $stored['enabled_department_ids'] ?? null;
        if (is_array($deptIds) && $deptIds !== []) {
            $deptIds = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $deptIds)));
            $deptIds = array_values(array_intersect($deptIds, $allowedDepts));
            if ($deptIds === []) {
                $deptIds = null;
            }
        } else {
            $deptIds = null;
        }

        return [
            'enabled_source_ids' => $sourceIds,
            'enabled_channel_types' => $channels,
            'enabled_department_ids' => $deptIds,
        ];
    }

    /**
     * @return list<int>
     */
    private function allowedSourceIds(User $user): array
    {
        if ($user->hasRole('admin')) {
            return SourceModel::query()->orderBy('id')->pluck('id')->map(fn (mixed $id) => (int) $id)->values()->all();
        }

        return $user->sources()->pluck('sources.id')->map(fn (mixed $id) => (int) $id)->values()->all();
    }

    /**
     * Departments the user may include in prefs (matches listing visibility semantics).
     *
     * @return list<int>
     */
    private function allowedDepartmentIds(User $user): array
    {
        if ($user->hasRole('admin')) {
            return DepartmentModel::query()->orderBy('id')->pluck('id')->map(fn (mixed $id) => (int) $id)->values()->all();
        }

        $sourceIds = $user->sources()->pluck('sources.id')->all();
        if ($sourceIds === []) {
            return [];
        }

        $pivotDeptIds = $user->departments()->pluck('departments.id')->map(fn (mixed $id) => (int) $id)->values()->all();
        if ($pivotDeptIds !== []) {
            return array_values(array_unique($pivotDeptIds));
        }

        return DepartmentModel::query()
            ->whereIn('source_id', $sourceIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('id')
            ->map(fn (mixed $id) => (int) $id)
            ->values()
            ->all();
    }
}

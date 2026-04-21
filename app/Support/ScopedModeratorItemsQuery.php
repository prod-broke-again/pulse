<?php

declare(strict_types=1);

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared list filtering for canned responses and quick links (scope + ownership).
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @param  Builder<TModel>  $query
 */
final class ScopedModeratorItemsQuery
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array{
     *     visibility?: string|null,
     *     source_id?: int|null,
     *     department_id?: int|null,
     *     scope_type?: string|null,
     *     scope_id?: int|null,
     *     chat_context?: bool|null,
     * }  $filters
     */
    public static function apply(Builder $query, User $user, array $filters): void
    {
        $visibility = $filters['visibility'] ?? 'all';
        if (! in_array($visibility, ['mine', 'shared', 'all'], true)) {
            $visibility = 'all';
        }

        if (! $user->isAdmin()) {
            self::applyOwnershipVisibility($query, $user, $visibility);
        }

        $explicitScopeType = $filters['scope_type'] ?? null;
        $explicitScopeId = isset($filters['scope_id']) ? (int) $filters['scope_id'] : null;
        if ($explicitScopeType !== null && $explicitScopeType !== '') {
            $query->where('scope_type', $explicitScopeType);
            if ($explicitScopeId !== null) {
                $query->where('scope_id', $explicitScopeId);
            }

            return;
        }

        $sourceId = isset($filters['source_id']) ? (int) $filters['source_id'] : null;
        $departmentId = isset($filters['department_id']) ? (int) $filters['department_id'] : null;
        $chatContext = (bool) ($filters['chat_context'] ?? false);

        if ($chatContext && ($sourceId !== null || $departmentId !== null)) {
            self::applyChatContextScopeFilter($query, $sourceId, $departmentId);

            return;
        }

        if ($sourceId !== null) {
            self::applySourceManagementFilter($query, $sourceId);
        }
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function applyOwnershipVisibility(Builder $query, User $user, string $visibility): void
    {
        if ($visibility === 'mine') {
            $query->where('owner_user_id', $user->id);

            return;
        }

        if ($visibility === 'shared') {
            $query->whereNull('owner_user_id')->where(function ($q) use ($user): void {
                self::applySharedReadableScopes($q, $user);
            });

            return;
        }

        $query->where(function ($q) use ($user): void {
            $q->where('owner_user_id', $user->id)
                ->orWhere(function ($q2) use ($user): void {
                    $q2->whereNull('owner_user_id')
                        ->where(function ($q3) use ($user): void {
                            self::applySharedReadableScopes($q3, $user);
                        });
                });
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function applySharedReadableScopes(Builder $query, User $user): void
    {
        $query->where(function ($q) use ($user): void {
            $q->where(function ($g): void {
                $g->whereNull('scope_type')->whereNull('scope_id');
            })->orWhere(function ($g) use ($user): void {
                $ids = $user->sources()->pluck('id')->all();
                if ($ids === []) {
                    $g->whereRaw('1 = 0');
                } else {
                    $g->where('scope_type', 'source')->whereIn('scope_id', $ids);
                }
            })->orWhere(function ($g) use ($user): void {
                $ids = $user->departments()->pluck('departments.id')->all();
                if ($ids === []) {
                    $g->whereRaw('1 = 0');
                } else {
                    $g->where('scope_type', 'department')->whereIn('scope_id', $ids);
                }
            });
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function applySourceManagementFilter(Builder $query, int $sourceId): void
    {
        $deptIds = DepartmentModel::query()
            ->where('source_id', $sourceId)
            ->pluck('id')
            ->all();

        $query->where(function ($q) use ($sourceId, $deptIds): void {
            $q->where(function ($g): void {
                $g->whereNull('scope_type')->whereNull('scope_id');
            })->orWhere(function ($g) use ($sourceId): void {
                $g->where('scope_type', 'source')->where('scope_id', $sourceId);
            });
            if ($deptIds !== []) {
                $q->orWhere(function ($g) use ($deptIds): void {
                    $g->where('scope_type', 'department')->whereIn('scope_id', $deptIds);
                });
            }
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function applyChatContextScopeFilter(Builder $query, ?int $sourceId, ?int $departmentId): void
    {
        $query->where(function ($q) use ($sourceId, $departmentId): void {
            $q->where(function ($g): void {
                $g->whereNull('scope_type')->whereNull('scope_id');
            });
            if ($sourceId !== null) {
                $q->orWhere(function ($g) use ($sourceId): void {
                    $g->where('scope_type', 'source')->where('scope_id', $sourceId);
                });
            }
            if ($departmentId !== null) {
                $q->orWhere(function ($g) use ($departmentId): void {
                    $g->where('scope_type', 'department')->where('scope_id', $departmentId);
                });
            }
        });
    }
}

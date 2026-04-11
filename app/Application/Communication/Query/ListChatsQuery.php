<?php

declare(strict_types=1);

namespace App\Application\Communication\Query;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListChatsQuery
{
    /**
     * @param array{
     *     tab?: string,
     *     source_id?: int|null,
     *     department_id?: int|null,
     *     search?: string|null,
     *     status?: string|null,
     *     channels?: list<string>|null,
     * } $filters
     */
    public function run(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->baseQuery($user, $filters);

        return $query->orderByDesc('updated_at')->paginate($perPage);
    }

    /**
     * @param array{
     *     source_id?: int|null,
     *     department_id?: int|null,
     *     search?: string|null,
     *     status?: string|null,
     *     channels?: list<string>|null,
     * } $filters
     * @return array{my: int, unassigned: int, all: int}
     */
    public function tabCounts(User $user, array $filters): array
    {
        return [
            'my' => $this->countForTab($user, $filters, 'my'),
            'unassigned' => $this->countForTab($user, $filters, 'unassigned'),
            'all' => $this->countForTab($user, $filters, 'all'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function countForTab(User $user, array $filters, string $tab): int
    {
        return $this->baseQuery($user, array_merge($filters, ['tab' => $tab]))->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseQuery(User $user, array $filters): Builder
    {
        $tab = $filters['tab'] ?? 'my';

        $query = ChatModel::query()
            ->with(['source', 'department', 'assignee', 'latestMessage'])
            ->withUnreadCountForUser($user);

        $this->applyStatusFilter($query, $filters);
        $this->applyVisibilityScope($query, $user);
        $this->applyTabFilter($query, $user, $tab);
        $this->applySourceFilter($query, $filters);
        $this->applyDepartmentFilter($query, $filters);
        $this->applyChannelFilter($query, $filters);
        $this->applySearchFilter($query, $filters);

        return $query;
    }

    /**
     * @param  array{status?: string|null}  $filters
     */
    private function applyStatusFilter(Builder $query, array $filters): void
    {
        $status = $filters['status'] ?? null;
        if ($status === 'all') {
            return;
        }
        if ($status === 'closed') {
            $query->where('status', 'closed');
        } else {
            $query->whereIn('status', ['new', 'active']);
        }
    }

    private function applyVisibilityScope(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $sourceIds = $user->sources()->pluck('id')->all();
        if ($sourceIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('source_id', $sourceIds);

        $deptIds = $user->departments()->pluck('departments.id')->all();
        if ($deptIds !== []) {
            $query->whereIn('department_id', $deptIds);
        }
    }

    private function applyTabFilter(Builder $query, User $user, string $tab): void
    {
        match ($tab) {
            'my' => $query->where('assigned_to', $user->id),
            'unassigned' => $query->whereNull('assigned_to'),
            'all' => null,
            default => $query->where('assigned_to', $user->id),
        };
    }

    /**
     * @param  array{source_id?: int|null}  $filters
     */
    private function applySourceFilter(Builder $query, array $filters): void
    {
        if (! empty($filters['source_id'])) {
            $query->where('source_id', (int) $filters['source_id']);
        }
    }

    /**
     * @param  array{department_id?: int|null}  $filters
     */
    private function applyDepartmentFilter(Builder $query, array $filters): void
    {
        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }
    }

    /**
     * @param  array{channels?: list<string>|null}  $filters
     */
    private function applyChannelFilter(Builder $query, array $filters): void
    {
        $channels = $filters['channels'] ?? null;
        if ($channels === null || $channels === []) {
            return;
        }

        $allowed = array_values(array_intersect($channels, ['tg', 'vk', 'web', 'max']));
        if ($allowed === []) {
            return;
        }

        $query->whereHas('source', fn (Builder $q) => $q->whereIn('type', $allowed));
    }

    /**
     * @param  array{search?: string|null}  $filters
     */
    private function applySearchFilter(Builder $query, array $filters): void
    {
        $search = $filters['search'] ?? null;
        if ($search === null || trim($search) === '') {
            return;
        }

        $term = '%'.trim($search).'%';
        $query->where(function (Builder $q) use ($term): void {
            $q->where('external_user_id', 'like', $term)
                ->orWhereJsonContains('user_metadata->name', trim($term, '%'))
                ->orWhereHas('messages', fn (Builder $mq) => $mq->where('text', 'like', $term)->limit(1));
        });
    }
}

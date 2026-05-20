<?php

declare(strict_types=1);

namespace App\Application\Communication\Query;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

final readonly class ListChatsQuery
{
    /**
     * @param array{
     *     tab?: string,
     *     source_id?: int|null,
     *     source_ids?: list<int>|null,
     *     department_id?: int|null,
     *     department_ids?: list<int>|null,
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
     *     source_ids?: list<int>|null,
     *     department_id?: int|null,
     *     department_ids?: list<int>|null,
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
     * @return array{all: array{open: int, unread: int}, departments: list<array{id: int, name: string, open: int, unread: int}>}
     */
    public function inboxSummary(User $user, array $filters): array
    {
        unset($filters['department_id'], $filters['department_ids']);
        $filters['tab'] = 'all';
        $filters['status'] = 'open';

        $userDepts = \App\Infrastructure\Persistence\Eloquent\DepartmentModel::query()
            ->where('is_active', true);

        if (! $user->isAdmin()) {
            $pivotDeptIds = $user->departments()->pluck('departments.id')->all();
            if ($pivotDeptIds !== []) {
                $userDepts->whereIn('id', $pivotDeptIds);
            } else {
                $sourceIds = $user->sources()->pluck('sources.id')->all();
                $userDepts->whereIn('source_id', $sourceIds);
            }
        }

        $departments = $userDepts->get(['id', 'name']);

        $baseQuery = $this->baseQuery($user, $filters);
        $baseQuery->setEagerLoads([]);

        $unreadSubquery = 'EXISTS (
            SELECT 1 FROM messages
            WHERE messages.chat_id = chats.id
              AND messages.sender_type = \'client\'
              AND messages.id > COALESCE((
                  SELECT curs.last_read_message_id
                  FROM chat_user_read_states AS curs
                  WHERE curs.chat_id = chats.id
                    AND curs.user_id = ?
                  LIMIT 1
              ), 0)
        )';

        $results = $baseQuery
            ->select('chats.department_id')
            ->selectRaw('COUNT(*) as open_count')
            ->selectRaw('SUM(CASE WHEN ' . $unreadSubquery . ' THEN 1 ELSE 0 END) as unread_count', [$user->id])
            ->groupBy('chats.department_id')
            ->get();

        $resultsMap = $results->keyBy('department_id');

        $summaryDepts = [];
        $totalOpen = 0;
        $totalUnread = 0;

        foreach ($departments as $dept) {
            $id = (int) $dept->id;
            $row = $resultsMap->get($id);
            $open = $row ? (int) $row->open_count : 0;
            $unread = $row ? (int) $row->unread_count : 0;

            $summaryDepts[] = [
                'id' => $id,
                'name' => (string) $dept->name,
                'open' => $open,
                'unread' => $unread,
            ];

            $totalOpen += $open;
            $totalUnread += $unread;
        }

        return [
            'all' => [
                'open' => $totalOpen,
                'unread' => $totalUnread,
            ],
            'departments' => $summaryDepts,
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
            ->whereHas('messages')
            ->with([
                'source',
                'department',
                'assignee',
                'latestMessage',
                'userReadStates' => function (HasMany $q) use ($user): void {
                    $q->where('user_id', $user->id);
                },
            ])
            ->withUnreadCountForUser($user);

        $this->applyStatusFilter($query, $filters);
        $this->applyVisibilityScope($query, $user);
        $this->applyTabFilter($query, $user, $tab);
        $this->applyNewFilters($query, $user, $filters);
        $this->applySourceFilter($query, $user, $filters);
        $this->applyDepartmentFilter($query, $user, $filters);
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
     * @param  array{assigned_to_me?: bool|int|string, unassigned_only?: bool|int|string, unread_only?: bool|int|string, chat_status?: string}  $filters
     */
    private function applyNewFilters(Builder $query, User $user, array $filters): void
    {
        if (isset($filters['assigned_to_me'])) {
            $assignedToMe = filter_var($filters['assigned_to_me'], FILTER_VALIDATE_BOOLEAN);
            if ($assignedToMe) {
                $query->where('assigned_to', $user->id);
            }
        }

        if (isset($filters['unassigned_only'])) {
            $unassignedOnly = filter_var($filters['unassigned_only'], FILTER_VALIDATE_BOOLEAN);
            if ($unassignedOnly) {
                $query->whereNull('assigned_to');
            }
        }

        if (isset($filters['unread_only'])) {
            $unreadOnly = filter_var($filters['unread_only'], FILTER_VALIDATE_BOOLEAN);
            if ($unreadOnly) {
                $query->whereHas('messages', function (Builder $q) use ($user): void {
                    $q->where('sender_type', 'client')
                        ->whereRaw(
                            'messages.id > COALESCE((
                                SELECT curs.last_read_message_id
                                FROM chat_user_read_states AS curs
                                WHERE curs.chat_id = messages.chat_id
                                  AND curs.user_id = ?
                                LIMIT 1
                            ), 0)',
                            [$user->id]
                        );
                });
            }
        }

        if (isset($filters['chat_status']) && in_array($filters['chat_status'], ['new', 'active'], true)) {
            $query->where('chats.status', $filters['chat_status']);
        }
    }

    /**
     * @param  array{source_id?: int|null, source_ids?: list<int>|array<int>|null}  $filters
     */
    private function applySourceFilter(Builder $query, User $user, array $filters): void
    {
        $multi = $filters['source_ids'] ?? null;
        if (is_array($multi) && $multi !== []) {
            $ids = $this->intersectSourceIdsForUser($user, $multi);
            if ($ids !== []) {
                $query->whereIn('source_id', $ids);
            }

            return;
        }

        if (! empty($filters['source_id'])) {
            $query->where('source_id', (int) $filters['source_id']);
        }
    }

    /**
     * @param  list<int|string>|array<int|string>  $requested
     * @return list<int>
     */
    private function intersectSourceIdsForUser(User $user, array $requested): array
    {
        $ids = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $requested)));
        if ($ids === []) {
            return [];
        }

        if ($user->isAdmin()) {
            return SourceModel::query()->whereIn('id', $ids)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        }

        $allowed = $user->sources()->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        return array_values(array_intersect($ids, $allowed));
    }

    /**
     * @param  array{department_id?: int|null, department_ids?: list<int>|array<int>|null}  $filters
     */
    private function applyDepartmentFilter(Builder $query, User $user, array $filters): void
    {
        $multi = $filters['department_ids'] ?? null;
        if (is_array($multi) && $multi !== []) {
            $ids = $this->intersectDepartmentIdsForUser($user, $multi);
            if ($ids !== []) {
                $query->whereIn('department_id', $ids);
            }

            return;
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }
    }

    /**
     * @param  list<int|string>|array<int|string>  $requested
     * @return list<int>
     */
    private function intersectDepartmentIdsForUser(User $user, array $requested): array
    {
        $ids = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $requested)));
        if ($ids === []) {
            return [];
        }

        if ($user->isAdmin()) {
            return $ids;
        }

        $sourceIds = $user->sources()->pluck('id')->all();
        if ($sourceIds === []) {
            return [];
        }

        $pivotDeptIds = $user->departments()->pluck('departments.id')->all();
        if ($pivotDeptIds !== []) {
            $allowed = array_map(static fn ($id): int => (int) $id, $pivotDeptIds);

            return array_values(array_intersect($ids, $allowed));
        }

        $allowed = DepartmentModel::query()
            ->whereIn('source_id', $sourceIds)
            ->where('is_active', true)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return array_values(array_intersect($ids, $allowed));
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

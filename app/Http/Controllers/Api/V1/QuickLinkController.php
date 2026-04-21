<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ListQuickLinksRequest;
use App\Http\Requests\Api\V1\ReorderQuickLinksRequest;
use App\Http\Requests\Api\V1\StoreQuickLinkRequest;
use App\Http\Requests\Api\V1\UpdateQuickLinkRequest;
use App\Http\Resources\Api\V1\QuickLinkResource;
use App\Models\QuickLink;
use App\Models\User;
use App\Support\ModeratorScopedItemAccess;
use App\Support\ModeratorSourceAccess;
use App\Support\ResolvesModeratorItemScope;
use App\Support\ScopedModeratorItemsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class QuickLinkController extends Controller
{
    public function index(ListQuickLinksRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', \App\Infrastructure\Persistence\Eloquent\ChatModel::class);

        /** @var User $user */
        $user = auth()->user();
        $validated = $request->validated();
        $includeInactive = (bool) ($validated['include_inactive'] ?? false);
        $sourceIdFilter = isset($validated['source_id']) ? (int) $validated['source_id'] : null;
        $departmentIdFilter = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
        $chatContext = (bool) ($validated['chat_context'] ?? false);

        $query = $this->baseQuery($user, $includeInactive);

        $filters = [
            'visibility' => $validated['visibility'] ?? 'all',
            'source_id' => $sourceIdFilter,
            'department_id' => $departmentIdFilter,
            'scope_type' => $validated['scope_type'] ?? null,
            'scope_id' => isset($validated['scope_id']) ? (int) $validated['scope_id'] : null,
            'chat_context' => $chatContext,
        ];

        ScopedModeratorItemsQuery::apply($query, $user, $filters);

        if ($sourceIdFilter !== null && ! $user->isAdmin()) {
            ModeratorSourceAccess::assertCanAccessSourceForRead($user, $sourceIdFilter);
        }

        $search = $validated['q'] ?? null;
        if ($search !== null && trim((string) $search) !== '') {
            $term = '%'.trim((string) $search).'%';
            $query->where(function ($q) use ($term): void {
                $q->where('title', 'like', $term)
                    ->orWhere('url', 'like', $term);
            });
        }

        return QuickLinkResource::collection(
            $query->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(StoreQuickLinkRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', \App\Infrastructure\Persistence\Eloquent\ChatModel::class);

        /** @var User $user */
        $user = auth()->user();
        $data = $request->validated();
        [$scopeType, $scopeId] = ResolvesModeratorItemScope::fromRequestArray($data);

        ModeratorScopedItemAccess::assertCanManageScope($user, $scopeType, $scopeId);

        $isShared = (bool) ($data['is_shared'] ?? false);
        $ownerUserId = $isShared ? null : $user->id;

        $row = QuickLink::create([
            'owner_user_id' => $ownerUserId,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'title' => $data['title'],
            'url' => $data['url'],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return (new QuickLinkResource($row))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateQuickLinkRequest $request, QuickLink $quickLink): JsonResponse
    {
        Gate::authorize('viewAny', \App\Infrastructure\Persistence\Eloquent\ChatModel::class);

        /** @var User $user */
        $user = auth()->user();
        $this->ensureCanManageQuickLink($user, $quickLink);

        $data = $request->validated();

        if (array_key_exists('scope_type', $data) || array_key_exists('scope_id', $data) || array_key_exists('source_id', $data)) {
            $merged = array_merge([
                'scope_type' => $quickLink->scope_type,
                'scope_id' => $quickLink->scope_id,
                'source_id' => $quickLink->scope_type === 'source' ? $quickLink->scope_id : null,
            ], $data);
            [$scopeType, $scopeId] = ResolvesModeratorItemScope::fromRequestArray($merged);
            ModeratorScopedItemAccess::assertCanManageScope($user, $scopeType, $scopeId);
            $quickLink->scope_type = $scopeType;
            $quickLink->scope_id = $scopeId;
        }

        if (array_key_exists('is_shared', $data)) {
            $isShared = (bool) $data['is_shared'];
            $quickLink->owner_user_id = $isShared ? null : $user->id;
        }

        if (isset($data['title'])) {
            $quickLink->title = $data['title'];
        }
        if (isset($data['url'])) {
            $quickLink->url = $data['url'];
        }
        if (isset($data['is_active'])) {
            $quickLink->is_active = (bool) $data['is_active'];
        }
        if (isset($data['sort_order'])) {
            $quickLink->sort_order = (int) $data['sort_order'];
        }

        $quickLink->save();

        return (new QuickLinkResource($quickLink->fresh()))->response();
    }

    public function destroy(QuickLink $quickLink): JsonResponse
    {
        Gate::authorize('viewAny', \App\Infrastructure\Persistence\Eloquent\ChatModel::class);

        /** @var User $user */
        $user = auth()->user();
        $this->ensureCanManageQuickLink($user, $quickLink);
        $quickLink->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function reorder(ReorderQuickLinksRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', \App\Infrastructure\Persistence\Eloquent\ChatModel::class);

        /** @var User $user */
        $user = auth()->user();
        $orders = $request->validated('orders');

        $ids = array_map(static fn (array $o): int => (int) $o['id'], $orders);
        $rows = QuickLink::query()->whereIn('id', $ids)->get()->keyBy('id');

        if ($rows->count() !== count(array_unique($ids))) {
            abort(422, 'Invalid quick link ids.');
        }

        foreach ($orders as $order) {
            $link = $rows->get((int) $order['id']);
            if ($link === null) {
                abort(422);
            }
            $this->ensureCanManageQuickLink($user, $link);
        }

        DB::transaction(function () use ($orders, $rows): void {
            foreach ($orders as $order) {
                /** @var QuickLink $link */
                $link = $rows->get((int) $order['id']);
                $link->sort_order = (int) $order['sort_order'];
                $link->save();
            }
        });

        return response()->json(['data' => ['ok' => true]]);
    }

    private function ensureCanManageQuickLink(User $user, QuickLink $row): void
    {
        ModeratorScopedItemAccess::assertCanManageExistingRow($user, $row->owner_user_id, [
            'scope_type' => $row->scope_type,
            'scope_id' => $row->scope_id,
        ]);
    }

    /** @param  Builder<QuickLink>  $query */
    private function baseQuery(User $user, bool $includeInactive): Builder
    {
        $query = QuickLink::query();

        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        return $query;
    }
}

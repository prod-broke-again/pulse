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
use App\Support\ModeratorSourceAccess;
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

        $query = $this->baseQuery($user, $includeInactive, $sourceIdFilter);

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
        $sourceId = isset($data['source_id']) ? (int) $data['source_id'] : null;

        ModeratorSourceAccess::assertCanManageSource($user, $sourceId);

        $row = QuickLink::create([
            'source_id' => $sourceId,
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
        if (array_key_exists('source_id', $data)) {
            $newSourceId = $data['source_id'] !== null ? (int) $data['source_id'] : null;
            ModeratorSourceAccess::assertCanManageSource($user, $newSourceId);
        }

        $quickLink->fill($data);
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
        ModeratorSourceAccess::assertCanManageSource($user, $row->source_id);
    }

    /** @param  Builder<QuickLink>  $query */
    private function baseQuery(User $user, bool $includeInactive, ?int $sourceIdFilter): Builder
    {
        $query = QuickLink::query();

        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        if (! $user->isAdmin()) {
            $sourceIds = $user->sources()->pluck('id')->all();
            $query->where(function ($q) use ($sourceIds): void {
                $q->whereNull('source_id')->orWhereIn('source_id', $sourceIds);
            });
        }

        if ($sourceIdFilter !== null) {
            if (! $user->isAdmin()) {
                ModeratorSourceAccess::assertCanAccessSourceForRead($user, $sourceIdFilter);
            }
            $query->where(function ($q) use ($sourceIdFilter): void {
                $q->where('source_id', $sourceIdFilter)->orWhereNull('source_id');
            });
        }

        return $query;
    }
}

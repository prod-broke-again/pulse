<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ListCannedResponsesRequest;
use App\Http\Requests\Api\V1\StoreCannedResponseRequest;
use App\Http\Requests\Api\V1\UpdateCannedResponseRequest;
use App\Http\Resources\Api\V1\CannedResponseResource;
use App\Models\CannedResponse;
use App\Models\User;
use App\Support\ModeratorScopedItemAccess;
use App\Support\ModeratorSourceAccess;
use App\Support\ResolvesModeratorItemScope;
use App\Support\ScopedModeratorItemsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class CannedResponseController extends Controller
{
    public function index(ListCannedResponsesRequest $request): AnonymousResourceCollection
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
                $q->where('code', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('text', 'like', $term);
            });
        }

        return CannedResponseResource::collection(
            $query->orderBy('code')->get()
        );
    }

    public function store(StoreCannedResponseRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', \App\Infrastructure\Persistence\Eloquent\ChatModel::class);

        /** @var User $user */
        $user = auth()->user();
        $data = $request->validated();
        [$scopeType, $scopeId] = ResolvesModeratorItemScope::fromRequestArray($data);

        ModeratorScopedItemAccess::assertCanManageScope($user, $scopeType, $scopeId);

        $isShared = (bool) ($data['is_shared'] ?? false);
        $ownerUserId = $isShared ? null : $user->id;

        $row = CannedResponse::create([
            'owner_user_id' => $ownerUserId,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'code' => $data['code'],
            'title' => $data['title'],
            'text' => $data['text'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return (new CannedResponseResource($row))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCannedResponseRequest $request, CannedResponse $cannedResponse): JsonResponse
    {
        Gate::authorize('viewAny', \App\Infrastructure\Persistence\Eloquent\ChatModel::class);

        /** @var User $user */
        $user = auth()->user();
        $this->ensureCanManageCanned($user, $cannedResponse);

        $data = $request->validated();

        if (array_key_exists('scope_type', $data) || array_key_exists('scope_id', $data) || array_key_exists('source_id', $data)) {
            $merged = array_merge([
                'scope_type' => $cannedResponse->scope_type,
                'scope_id' => $cannedResponse->scope_id,
                'source_id' => $cannedResponse->scope_type === 'source' ? $cannedResponse->scope_id : null,
            ], $data);
            [$scopeType, $scopeId] = ResolvesModeratorItemScope::fromRequestArray($merged);
            ModeratorScopedItemAccess::assertCanManageScope($user, $scopeType, $scopeId);
            $cannedResponse->scope_type = $scopeType;
            $cannedResponse->scope_id = $scopeId;
        }

        if (array_key_exists('is_shared', $data)) {
            $isShared = (bool) $data['is_shared'];
            $cannedResponse->owner_user_id = $isShared ? null : $user->id;
        }

        if (isset($data['code'])) {
            $cannedResponse->code = $data['code'];
        }
        if (isset($data['title'])) {
            $cannedResponse->title = $data['title'];
        }
        if (isset($data['text'])) {
            $cannedResponse->text = $data['text'];
        }
        if (isset($data['is_active'])) {
            $cannedResponse->is_active = (bool) $data['is_active'];
        }

        $cannedResponse->save();

        return (new CannedResponseResource($cannedResponse->fresh()))->response();
    }

    public function destroy(CannedResponse $cannedResponse): JsonResponse
    {
        Gate::authorize('viewAny', \App\Infrastructure\Persistence\Eloquent\ChatModel::class);

        /** @var User $user */
        $user = auth()->user();
        $this->ensureCanManageCanned($user, $cannedResponse);
        $cannedResponse->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    private function ensureCanManageCanned(User $user, CannedResponse $row): void
    {
        ModeratorScopedItemAccess::assertCanManageExistingRow($user, $row->owner_user_id, [
            'scope_type' => $row->scope_type,
            'scope_id' => $row->scope_id,
        ]);
    }

    /** @param  Builder<CannedResponse>  $query */
    private function baseQuery(User $user, bool $includeInactive): Builder
    {
        $query = CannedResponse::query();

        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        return $query;
    }
}

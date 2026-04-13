<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ListCannedResponsesRequest;
use App\Http\Requests\Api\V1\StoreCannedResponseRequest;
use App\Http\Requests\Api\V1\UpdateCannedResponseRequest;
use App\Http\Resources\Api\V1\CannedResponseResource;
use App\Models\CannedResponse;
use App\Models\User;
use App\Support\ModeratorSourceAccess;
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

        $query = $this->baseQuery($user, $includeInactive, $sourceIdFilter);

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
        $sourceId = isset($data['source_id']) ? (int) $data['source_id'] : null;

        ModeratorSourceAccess::assertCanManageSource($user, $sourceId);

        $row = CannedResponse::create([
            'source_id' => $sourceId,
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
        if (array_key_exists('source_id', $data)) {
            $newSourceId = $data['source_id'] !== null ? (int) $data['source_id'] : null;
            ModeratorSourceAccess::assertCanManageSource($user, $newSourceId);
        }

        $cannedResponse->fill($data);
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
        ModeratorSourceAccess::assertCanManageSource($user, $row->source_id);
    }

    /** @param  Builder<CannedResponse>  $query */
    private function baseQuery(User $user, bool $includeInactive, ?int $sourceIdFilter): Builder
    {
        $query = CannedResponse::query();

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

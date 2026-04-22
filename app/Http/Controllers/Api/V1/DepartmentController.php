<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ListDepartmentsRequest;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Models\User;
use App\Support\DepartmentIcons;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class DepartmentController extends Controller
{
    public function index(ListDepartmentsRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', \App\Infrastructure\Persistence\Eloquent\ChatModel::class);

        /** @var User $user */
        $user = auth()->user();
        $sourceId = (int) $request->validated('source_id');

        if (! $user->isAdmin()) {
            $sourceIds = $user->sources()->pluck('id')->all();
            if (! in_array($sourceId, $sourceIds, true)) {
                abort(403);
            }
        }

        $query = DepartmentModel::query()
            ->where('source_id', $sourceId)
            ->where('is_active', true)
            ->orderBy('name');

        if (! $user->isAdmin()) {
            $deptIds = $user->departments()->pluck('departments.id')->all();
            if ($deptIds === []) {
                return response()->json(['data' => []]);
            }
            $query->whereIn('id', $deptIds);
        }

        $rows = $query->get(['id', 'name', 'slug', 'icon']);

        return response()->json([
            'data' => $rows->map(static fn (DepartmentModel $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'slug' => $d->slug,
                'icon' => DepartmentIcons::normalize($d->icon),
            ])->values()->all(),
        ]);
    }

    /**
     * All departments the current user may see (across assigned sources), for inbox filters / settings.
     */
    public function indexForCurrentUser(): JsonResponse
    {
        Gate::authorize('viewAny', \App\Infrastructure\Persistence\Eloquent\ChatModel::class);

        /** @var User $user */
        $user = auth()->user();

        $query = DepartmentModel::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($user->isAdmin()) {
            $rows = $query->get(['id', 'name', 'slug', 'icon', 'source_id']);
        } else {
            $sourceIds = $user->sources()->pluck('id')->all();
            if ($sourceIds === []) {
                return response()->json(['data' => []]);
            }

            $query->whereIn('source_id', $sourceIds);

            $pivotDeptIds = $user->departments()->pluck('departments.id')->all();
            if ($pivotDeptIds !== []) {
                $query->whereIn('id', array_map(static fn ($id): int => (int) $id, $pivotDeptIds));
            }

            $rows = $query->get(['id', 'name', 'slug', 'icon', 'source_id']);
        }

        return response()->json([
            'data' => $rows->map(static fn (DepartmentModel $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'slug' => $d->slug,
                'icon' => DepartmentIcons::normalize($d->icon),
                'source_id' => $d->source_id,
            ])->values()->all(),
        ]);
    }
}

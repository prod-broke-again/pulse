<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Communication\Query\AnalyticsOverviewQuery;
use App\Http\Requests\Api\V1\AnalyticsOverviewRequest;
use App\Models\User;
use App\Support\ModeratorSourceAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class AnalyticsController extends Controller
{
    public function overview(AnalyticsOverviewRequest $request, AnalyticsOverviewQuery $query): JsonResponse
    {
        Gate::authorize('viewAny', \App\Infrastructure\Persistence\Eloquent\ChatModel::class);

        /** @var User $user */
        $user = auth()->user();
        $validated = $request->validated();

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();

        $sourceId = isset($validated['source_id']) ? (int) $validated['source_id'] : null;
        if ($sourceId !== null && ! $user->isAdmin()) {
            ModeratorSourceAccess::assertCanAccessSourceForRead($user, $sourceId);
        }

        $data = $query->run($user, $from, $to, $sourceId);

        return response()->json(['data' => $data]);
    }
}

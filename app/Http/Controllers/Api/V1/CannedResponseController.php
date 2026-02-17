<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\CannedResponseResource;
use App\Models\CannedResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class CannedResponseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CannedResponse::where('is_active', true);

        $sourceId = $request->query('source_id');
        if ($sourceId !== null) {
            $query->where(function ($q) use ($sourceId): void {
                $q->where('source_id', (int) $sourceId)
                    ->orWhereNull('source_id');
            });
        }

        $search = $request->query('q');
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
}

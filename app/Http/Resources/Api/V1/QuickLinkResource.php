<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\QuickLink */
final class QuickLinkResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isShared = $this->owner_user_id === null;

        return [
            'id' => $this->id,
            'owner_user_id' => $this->owner_user_id,
            'is_shared' => $isShared,
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_id,
            'source_id' => $this->scope_type === 'source' ? $this->scope_id : null,
            'title' => $this->title,
            'url' => $this->url,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}

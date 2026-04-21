<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuickLinks\Pages;

use App\Filament\Resources\QuickLinks\QuickLinkResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuickLink extends CreateRecord
{
    protected static string $resource = QuickLinkResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['scope_type'] ?? '') === '') {
            $data['scope_type'] = null;
            $data['scope_id'] = null;
        }

        $data['owner_user_id'] = null;

        return $data;
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\CannedResponses\Pages;

use App\Filament\Resources\CannedResponses\CannedResponseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCannedResponse extends CreateRecord
{
    protected static string $resource = CannedResponseResource::class;

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

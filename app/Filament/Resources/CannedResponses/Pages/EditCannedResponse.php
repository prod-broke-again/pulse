<?php

declare(strict_types=1);

namespace App\Filament\Resources\CannedResponses\Pages;

use App\Filament\Resources\CannedResponses\CannedResponseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCannedResponse extends EditRecord
{
    protected static string $resource = CannedResponseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (($data['scope_type'] ?? null) === null) {
            $data['scope_type'] = '';
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['scope_type'] ?? '') === '') {
            $data['scope_type'] = null;
            $data['scope_id'] = null;
        }

        return $data;
    }
}

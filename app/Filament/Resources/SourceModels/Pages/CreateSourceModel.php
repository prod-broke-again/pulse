<?php

namespace App\Filament\Resources\SourceModels\Pages;

use App\Filament\Resources\SourceModels\Concerns\MapsSourceConnectionSettings;
use App\Filament\Resources\SourceModels\SourceModelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSourceModel extends CreateRecord
{
    use MapsSourceConnectionSettings;

    protected static string $resource = SourceModelResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = parent::mutateFormDataBeforeCreate($data);

        return $this->mapConnectionSettingsBeforePersist($data);
    }
}

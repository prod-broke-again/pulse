<?php

namespace App\Filament\Resources\SourceModels\Pages;

use App\Filament\Resources\SourceModels\Concerns\MapsSourceConnectionSettings;
use App\Filament\Resources\SourceModels\SourceModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSourceModel extends EditRecord
{
    use MapsSourceConnectionSettings;

    protected static string $resource = SourceModelResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        return $this->mapConnectionSettingsBeforeFill($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = parent::mutateFormDataBeforeSave($data);

        return $this->mapConnectionSettingsBeforePersist($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

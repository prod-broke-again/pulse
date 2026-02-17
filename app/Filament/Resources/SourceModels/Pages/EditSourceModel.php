<?php

namespace App\Filament\Resources\SourceModels\Pages;

use App\Filament\Resources\SourceModels\SourceModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSourceModel extends EditRecord
{
    protected static string $resource = SourceModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

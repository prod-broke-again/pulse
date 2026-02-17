<?php

namespace App\Filament\Resources\SourceModels\Pages;

use App\Filament\Resources\SourceModels\SourceModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSourceModels extends ListRecords
{
    protected static string $resource = SourceModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

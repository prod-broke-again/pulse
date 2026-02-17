<?php

namespace App\Filament\Resources\DepartmentModels\Pages;

use App\Filament\Resources\DepartmentModels\DepartmentModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDepartmentModels extends ListRecords
{
    protected static string $resource = DepartmentModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

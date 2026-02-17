<?php

namespace App\Filament\Resources\DepartmentModels\Pages;

use App\Filament\Resources\DepartmentModels\DepartmentModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDepartmentModel extends EditRecord
{
    protected static string $resource = DepartmentModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

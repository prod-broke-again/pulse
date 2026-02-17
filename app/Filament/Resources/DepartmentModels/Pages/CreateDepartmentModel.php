<?php

namespace App\Filament\Resources\DepartmentModels\Pages;

use App\Filament\Resources\DepartmentModels\DepartmentModelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDepartmentModel extends CreateRecord
{
    protected static string $resource = DepartmentModelResource::class;
}

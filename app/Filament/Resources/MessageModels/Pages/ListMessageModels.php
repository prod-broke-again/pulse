<?php

namespace App\Filament\Resources\MessageModels\Pages;

use App\Filament\Resources\MessageModels\MessageModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMessageModels extends ListRecords
{
    protected static string $resource = MessageModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

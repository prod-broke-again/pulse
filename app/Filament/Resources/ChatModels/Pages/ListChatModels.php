<?php

namespace App\Filament\Resources\ChatModels\Pages;

use App\Filament\Resources\ChatModels\ChatModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChatModels extends ListRecords
{
    protected static string $resource = ChatModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

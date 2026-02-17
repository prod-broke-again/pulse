<?php

namespace App\Filament\Resources\ChatModels\Pages;

use App\Filament\Resources\ChatModels\ChatModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChatModel extends EditRecord
{
    protected static string $resource = ChatModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

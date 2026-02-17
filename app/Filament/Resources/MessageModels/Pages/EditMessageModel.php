<?php

namespace App\Filament\Resources\MessageModels\Pages;

use App\Filament\Resources\MessageModels\MessageModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMessageModel extends EditRecord
{
    protected static string $resource = MessageModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\ChatModels\Pages;

use App\Filament\Resources\ChatModels\ChatModelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChatModel extends CreateRecord
{
    protected static string $resource = ChatModelResource::class;
}

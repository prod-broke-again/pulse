<?php

namespace App\Filament\Resources\MessageModels\Pages;

use App\Filament\Resources\MessageModels\MessageModelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMessageModel extends CreateRecord
{
    protected static string $resource = MessageModelResource::class;
}

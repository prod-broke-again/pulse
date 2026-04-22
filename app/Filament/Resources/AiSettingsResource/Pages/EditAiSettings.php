<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiSettingsResource\Pages;

use App\Filament\Resources\AiSettingsResource;
use Filament\Resources\Pages\EditRecord;

class EditAiSettings extends EditRecord
{
    protected static string $resource = AiSettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // no delete
        ];
    }
}

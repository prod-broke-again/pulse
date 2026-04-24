<?php

declare(strict_types=1);

namespace App\Filament\Resources\WidgetGlobalSettingsResource\Pages;

use App\Filament\Resources\WidgetGlobalSettingsResource;
use Filament\Resources\Pages\EditRecord;

class EditWidgetGlobalSettings extends EditRecord
{
    protected static string $resource = WidgetGlobalSettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // singleton — без удаления
        ];
    }
}

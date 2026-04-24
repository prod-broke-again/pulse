<?php

declare(strict_types=1);

namespace App\Filament\Resources\WidgetGlobalSettingsResource\Pages;

use App\Filament\Resources\WidgetGlobalSettingsResource;
use App\Models\WidgetGlobalSettings;
use Filament\Resources\Pages\ListRecords;

class ListWidgetGlobalSettings extends ListRecords
{
    protected static string $resource = WidgetGlobalSettingsResource::class;

    public function mount(): void
    {
        parent::mount();
        WidgetGlobalSettings::singleton();
        $this->redirect(WidgetGlobalSettingsResource::getUrl('edit', ['record' => WidgetGlobalSettings::DEFAULT_ID]));
    }
}

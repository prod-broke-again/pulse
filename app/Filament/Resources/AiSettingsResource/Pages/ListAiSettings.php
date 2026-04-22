<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiSettingsResource\Pages;

use App\Filament\Resources\AiSettingsResource;
use App\Models\AiSettings;
use Filament\Resources\Pages\ListRecords;

class ListAiSettings extends ListRecords
{
    protected static string $resource = AiSettingsResource::class;

    public function mount(): void
    {
        parent::mount();
        AiSettings::singleton();
        $this->redirect(AiSettingsResource::getUrl('edit', ['record' => 1]));
    }
}

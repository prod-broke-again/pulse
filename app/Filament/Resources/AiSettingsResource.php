<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AiSettingsResource\Pages\EditAiSettings;
use App\Filament\Resources\AiSettingsResource\Pages\ListAiSettings;
use App\Models\AiSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AiSettingsResource extends Resource
{
    protected static ?string $model = AiSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'Интеграции';

    protected static ?string $navigationLabel = 'AI (промпты)';

    protected static ?string $modelLabel = 'Настройки AI';

    protected static ?string $pluralModelLabel = 'Настройки AI';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('web_max_auto_replies')
                    ->label('Лимит (web)'),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('extra_kickoff_instructions')
                ->label('Доп. инструкции в kickoff (к первым сообщениям)')
                ->rows(4)
                ->columnSpanFull(),
            Textarea::make('autoreply_rules')
                ->label('Правила автоответа')
                ->rows(4)
                ->columnSpanFull(),
            TextInput::make('web_max_auto_replies')
                ->label('Лимит AI-ответов (web-виджет)')
                ->numeric()
                ->minValue(1)
                ->maxValue(20)
                ->required(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiSettings::route('/'),
            'edit' => EditAiSettings::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

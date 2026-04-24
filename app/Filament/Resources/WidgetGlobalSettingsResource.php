<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WidgetGlobalSettingsResource\Pages\EditWidgetGlobalSettings;
use App\Filament\Resources\WidgetGlobalSettingsResource\Pages\ListWidgetGlobalSettings;
use App\Models\WidgetGlobalSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class WidgetGlobalSettingsResource extends Resource
{
    protected static ?string $model = WidgetGlobalSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Интеграции';

    protected static ?string $navigationLabel = 'Web-виджет';

    protected static ?string $modelLabel = 'Web-виджет';

    protected static ?string $pluralModelLabel = 'Web-виджет';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('enabled')
                    ->label('Включён')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Да' : 'Нет')
                    ->sortable(),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('enabled')
                ->label('Виджет на сайтах включён')
                ->helperText('Если выключено: вместо чата показывается заглушка с ссылками. Env WIDGET_ENABLED может перекрыть эту настройку.')
                ->default(true)
                ->columnSpanFull(),
            TextInput::make('disabled_title')
                ->label('Заголовок заглушки')
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('disabled_text')
                ->label('Текст заглушки')
                ->rows(4)
                ->columnSpanFull(),
            TextInput::make('telegram_url')
                ->label('Ссылка Telegram')
                ->url()
                ->maxLength(500)
                ->columnSpanFull(),
            TextInput::make('vk_url')
                ->label('Ссылка VK')
                ->url()
                ->maxLength(500)
                ->columnSpanFull(),
            TextInput::make('max_url')
                ->label('Ссылка MAX')
                ->url()
                ->maxLength(500)
                ->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWidgetGlobalSettings::route('/'),
            'edit' => EditWidgetGlobalSettings::route('/{record}/edit'),
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

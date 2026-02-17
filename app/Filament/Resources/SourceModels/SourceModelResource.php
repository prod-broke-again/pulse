<?php

declare(strict_types=1);

namespace App\Filament\Resources\SourceModels;

use App\Filament\Resources\SourceModels\Pages\CreateSourceModel;
use App\Filament\Resources\SourceModels\Pages\EditSourceModel;
use App\Filament\Resources\SourceModels\Pages\ListSourceModels;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SourceModelResource extends Resource
{
    protected static ?string $model = SourceModel::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-signal';

    protected static string|\UnitEnum|null $navigationGroup = 'Интеграции';

    protected static ?string $navigationLabel = 'Источники';

    protected static ?string $modelLabel = 'Источник';

    protected static ?string $pluralModelLabel = 'Источники';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Название проекта / источника')
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->label('Тип')
                ->options([
                    'web' => 'Веб-виджет',
                    'vk' => 'Сообщество ВКонтакте',
                    'tg' => 'Telegram-бот',
                ])
                ->required(),
            TextInput::make('identifier')
                ->label('Идентификатор')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('secret_key')
                ->label('Секретный ключ')
                ->password()
                ->maxLength(255)
                ->helperText('VK: payload.secret, Telegram: X-Telegram-Bot-Api-Secret-Token'),
            KeyValue::make('settings')
                ->label('Настройки подключения')
                ->keyLabel('Ключ')
                ->valueLabel('Значение')
                ->reorderable(),
            Placeholder::make('integration_wizard')
                ->label('Мастер подключения')
                ->content(function (?SourceModel $record): string {
                    if ($record === null) {
                        return '1) Сначала сохраните источник. 2) Создайте отделы. 3) Используйте URL вебхука ниже в настройках бота.';
                    }

                    $base = rtrim((string) config('app.url'), '/');
                    $webhook = match ($record->type) {
                        'vk' => $base . '/webhook/vk/' . $record->id,
                        'tg' => $base . '/webhook/telegram/' . $record->id,
                        default => $base . '/api/widget/session',
                    };

                    return "Webhook / endpoint:\n{$webhook}\n\nДля встраивания виджета: {$base}/widget/pulse-widget.js";
                }),
            Select::make('users')
                ->label('Модераторы / Администраторы')
                ->relationship('users', 'name')
                ->multiple()
                ->searchable()
                ->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'vk' => 'info',
                        'tg' => 'success',
                        'web' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'vk' => 'ВКонтакте',
                        'tg' => 'Telegram',
                        'web' => 'Веб',
                        default => $state,
                    }),
                TextColumn::make('identifier')
                    ->label('Идентификатор')
                    ->searchable(),
                TextColumn::make('users.name')
                    ->label('Пользователи')
                    ->badge()
                    ->separator(','),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Изменить'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Удалить выбранные'),
                ]),
            ])
            ->defaultSort('id')
            ->emptyStateHeading('Источники не найдены')
            ->emptyStateDescription('Создайте первый источник для начала работы.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSourceModels::route('/'),
            'create' => CreateSourceModel::route('/create'),
            'edit' => EditSourceModel::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}

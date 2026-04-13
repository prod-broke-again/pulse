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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

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
                    'max' => 'Мессенджер MAX',
                ])
                ->live()
                ->required(),
            TextInput::make('identifier')
                ->label('Идентификатор')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('secret_key')
                ->label('Секретный ключ')
                ->password()
                ->revealable()
                ->maxLength(255)
                ->helperText('VK: payload.secret (тот же «Секретный ключ», что в Callback API сообщества), Telegram: X-Telegram-Bot-Api-Secret-Token, MAX: X-Max-Bot-Secret'),
            Group::make([
                Textarea::make('access_token')
                    ->label('ВК: токен доступа сообщества (access_token)')
                    ->helperText('Ключ API из настроек сообщества ВК (хранится в JSON settings).')
                    ->rows(4)
                    ->columnSpanFull()
                    ->nullable(),
                TextInput::make('group_id')
                    ->label('ВК: ID сообщества (group_id)')
                    ->numeric()
                    ->nullable(),
                TextInput::make('vk_callback_confirmation')
                    ->label('ВК: строка подтверждения Callback API')
                    ->helperText('Текст из блока «Подтверждение адреса сервера» (не JSON).')
                    ->maxLength(255)
                    ->nullable(),
                TextInput::make('public_app_url')
                    ->label('Публичный URL приложения (для подсказки вебхука)')
                    ->helperText('Если задан — в «Мастере подключения» ниже подставится этот хост вместо APP_URL (например https://pulse.appp-psy.ru).')
                    ->url()
                    ->maxLength(512)
                    ->columnSpanFull()
                    ->nullable(),
            ])
                ->statePath('settings')
                ->visible(fn (callable $get): bool => $get('type') === 'vk')
                ->columnSpanFull(),
            Placeholder::make('settings_json_preview')
                ->label('JSON settings (как сохранено в БД)')
                ->helperText('Только чтение. Обновляется после «Сохранить»; несохранённые правки в полях выше в JSON не попадут.')
                ->content(function (?SourceModel $record): HtmlString {
                    $settings = $record?->settings ?? [];
                    $json = json_encode(
                        is_array($settings) ? $settings : [],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
                    );

                    return new HtmlString(
                        '<pre class="fi-input-wrp rounded-lg border border-gray-200 bg-gray-50 p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap break-all text-gray-900 dark:border-white/10 dark:bg-white/5 dark:text-gray-100 max-h-96 overflow-y-auto">'
                        .e($json)
                        .'</pre>',
                    );
                })
                ->visible(fn (callable $get): bool => $get('type') === 'vk')
                ->columnSpanFull(),
            KeyValue::make('connection_settings')
                ->label('Настройки подключения')
                ->keyLabel('Ключ')
                ->valueLabel('Значение')
                ->reorderable()
                ->hidden(fn (callable $get): bool => $get('type') === 'vk')
                ->dehydrated(fn (callable $get): bool => $get('type') !== 'vk'),
            Placeholder::make('integration_wizard')
                ->label('Мастер подключения')
                ->content(function (?SourceModel $record): string {
                    if ($record === null) {
                        return '1) Сначала сохраните источник. 2) Создайте отделы. 3) Используйте URL вебхука ниже в настройках бота.';
                    }

                    $publicUrl = $record->settings['public_app_url'] ?? null;
                    $base = rtrim((string) (is_string($publicUrl) && $publicUrl !== '' ? $publicUrl : config('app.url')), '/');
                    $webhook = match ($record->type) {
                        'vk' => $base.'/webhook/vk/'.$record->id,
                        'tg' => $base.'/webhook/telegram/'.$record->id,
                        'max' => $base.'/webhook/max/'.$record->id,
                        default => $base.'/api/widget/session',
                    };

                    $vkExtra = $record->type === 'vk'
                        ? "\n\nВК: в поле «Секретный ключ» укажите тот же ключ, что в Callback API сообщества; в «строка подтверждения» — значение из экрана подтверждения."
                        : '';

                    return "Webhook / endpoint:\n{$webhook}{$vkExtra}\n\nДля встраивания виджета: {$base}/widget/pulse-widget.js";
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
                        'max' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'vk' => 'ВКонтакте',
                        'tg' => 'Telegram',
                        'web' => 'Веб',
                        'max' => 'MAX',
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

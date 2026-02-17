<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\SourceModel;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Integration';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255)->label('Project / Source name'),
            Select::make('type')->options([
                'web' => 'Web Widget',
                'vk' => 'VK Community',
                'tg' => 'Telegram Bot',
            ])->required(),
            TextInput::make('identifier')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('secret_key')
                ->password()
                ->maxLength(255)
                ->helperText('VK: payload.secret, Telegram: X-Telegram-Bot-Api-Secret-Token'),
            KeyValue::make('settings')
                ->label('Connection settings')
                ->keyLabel('Key')
                ->valueLabel('Value')
                ->reorderable(),
            Placeholder::make('integration_wizard')
                ->label('Connection Wizard')
                ->content(function (?SourceModel $record): string {
                    if ($record === null) {
                        return '1) Save source first. 2) Create departments. 3) Use webhook URL below in bot settings.';
                    }

                    $base = rtrim((string) config('app.url'), '/');
                    $webhook = match ($record->type) {
                        'vk' => $base . '/webhook/vk/' . $record->id,
                        'tg' => $base . '/webhook/telegram/' . $record->id,
                        default => $base . '/api/widget/session',
                    };

                    return "Webhook / endpoint:\n{$webhook}\n\nFor widget embed use: {$base}/widget/pulse-widget.js";
                }),
            Select::make('users')
                ->label('Moderators / Admins')
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
                TextColumn::make('id'),
                TextColumn::make('name'),
                TextColumn::make('type'),
                TextColumn::make('identifier'),
                TextColumn::make('users.name')->label('Users')->badge()->separator(','),
            ])
            ->defaultSort('id');
    }

    public static function getRelations(): array
    {
        return [];
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

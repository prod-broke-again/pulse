<?php

declare(strict_types=1);

namespace App\Filament\Resources\MessageModels;

use App\Filament\Resources\MessageModels\Pages\CreateMessageModel;
use App\Filament\Resources\MessageModels\Pages\EditMessageModel;
use App\Filament\Resources\MessageModels\Pages\ListMessageModels;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessageModelResource extends Resource
{
    protected static ?string $model = MessageModel::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'Коммуникации';

    protected static ?string $navigationLabel = 'Сообщения';

    protected static ?string $modelLabel = 'Сообщение';

    protected static ?string $pluralModelLabel = 'Сообщения';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('chat_id')
                ->relationship('chat', 'id')
                ->label('Чат')
                ->required(),
            Select::make('sender_id')
                ->relationship('sender', 'name')
                ->label('Отправитель'),
            Select::make('sender_type')
                ->label('Тип отправителя')
                ->options([
                    'client' => 'Клиент',
                    'moderator' => 'Модератор',
                    'system' => 'Система',
                ])
                ->required(),
            TextInput::make('text')
                ->label('Текст')
                ->required()
                ->columnSpanFull(),
            Checkbox::make('is_read')
                ->label('Прочитано'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('chat.id')
                    ->label('Чат')
                    ->sortable(),
                TextColumn::make('sender_type')
                    ->label('Отправитель')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'client' => 'info',
                        'moderator' => 'success',
                        'system' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'client' => 'Клиент',
                        'moderator' => 'Модератор',
                        'system' => 'Система',
                        default => $state,
                    }),
                TextColumn::make('text')
                    ->label('Текст')
                    ->limit(50)
                    ->searchable(),
                IconColumn::make('is_read')
                    ->label('Прочитано')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
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
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Сообщения не найдены')
            ->emptyStateDescription('Пока нет ни одного сообщения.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessageModels::route('/'),
            'create' => CreateMessageModel::route('/create'),
            'edit' => EditMessageModel::route('/{record}/edit'),
        ];
    }
}

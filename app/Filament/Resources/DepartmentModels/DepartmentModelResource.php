<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepartmentModels;

use App\Filament\Resources\DepartmentModels\Pages\CreateDepartmentModel;
use App\Filament\Resources\DepartmentModels\Pages\EditDepartmentModel;
use App\Filament\Resources\DepartmentModels\Pages\ListDepartmentModels;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepartmentModelResource extends Resource
{
    protected static ?string $model = DepartmentModel::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Интеграции';

    protected static ?string $navigationLabel = 'Отделы';

    protected static ?string $modelLabel = 'Отдел';

    protected static ?string $pluralModelLabel = 'Отделы';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('source_id')
                ->relationship('source', 'name')
                ->label('Источник')
                ->required(),
            TextInput::make('name')
                ->label('Название')
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->label('Слаг')
                ->required()
                ->maxLength(255),
            Toggle::make('is_active')
                ->label('Активен')
                ->default(true),
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
                TextColumn::make('source.name')
                    ->label('Источник')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Слаг')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
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
            ->emptyStateHeading('Отделы не найдены')
            ->emptyStateDescription('Создайте первый отдел для начала работы.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartmentModels::route('/'),
            'create' => CreateDepartmentModel::route('/create'),
            'edit' => EditDepartmentModel::route('/{record}/edit'),
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

<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $model = User::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Forms\Components\TextInput::make('name')->required()->maxLength(255),
            \Filament\Forms\Components\TextInput::make('email')->email()->required()->maxLength(255),
            \Filament\Forms\Components\Select::make('roles')
                ->relationship('roles', 'name')
                ->multiple()
                ->preload(),
            \Filament\Forms\Components\Select::make('sources')
                ->label('Projects (Sources)')
                ->relationship('sources', 'name')
                ->multiple()
                ->searchable()
                ->preload(),
            \Filament\Forms\Components\Select::make('departments')
                ->label('Departments (optional)')
                ->relationship('departments', 'name')
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
                TextColumn::make('email'),
                TextColumn::make('roles.name')->badge()->separator(','),
                TextColumn::make('sources.name')->badge()->separator(',')->label('Projects'),
                TextColumn::make('departments.name')->badge()->separator(',')->label('Departments'),
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

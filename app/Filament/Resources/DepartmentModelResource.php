<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Integration';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('source_id')
                ->relationship('source', 'name')
                ->required(),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('slug')->required()->maxLength(255),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('source.name'),
                TextColumn::make('name'),
                TextColumn::make('slug'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('id');
    }

    public static function getRelations(): array
    {
        return [];
    }
}

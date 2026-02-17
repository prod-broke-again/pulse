<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\SourceModel;
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
            TextInput::make('name')->required()->maxLength(255),
            Select::make('type')->options([
                'web' => 'Web',
                'vk' => 'VK',
                'tg' => 'Telegram',
            ])->required(),
            TextInput::make('identifier')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('secret_key')->password()->maxLength(255),
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
            ])
            ->defaultSort('id');
    }

    public static function getRelations(): array
    {
        return [];
    }
}

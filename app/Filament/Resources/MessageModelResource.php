<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\MessageModel;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Communication';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('chat_id')->relationship('chat', 'id')->required(),
            Select::make('sender_id')->relationship('sender', 'name'),
            Select::make('sender_type')->options([
                'client' => 'Client',
                'moderator' => 'Moderator',
                'system' => 'System',
            ])->required(),
            TextInput::make('text')->required()->columnSpanFull(),
            \Filament\Forms\Components\Checkbox::make('is_read'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('chat.id'),
                TextColumn::make('sender_type'),
                TextColumn::make('text')->limit(40),
                IconColumn::make('is_read')->boolean(),
            ])
            ->defaultSort('id');
    }

    public static function getRelations(): array
    {
        return [];
    }
}

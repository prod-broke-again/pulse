<?php

declare(strict_types=1);

namespace App\Filament\Resources\CannedResponses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CannedResponseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('source_id')
                    ->relationship('source', 'name')
                    ->label('Источник')
                    ->helperText('Пусто — глобальный шаблон для всех источников'),
                TextInput::make('code')
                    ->label('Код')
                    ->required()
                    ->maxLength(255),
                TextInput::make('title')
                    ->label('Название')
                    ->required()
                    ->maxLength(500),
                Textarea::make('text')
                    ->label('Текст')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),
            ]);
    }
}

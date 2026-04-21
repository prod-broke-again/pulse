<?php

declare(strict_types=1);

namespace App\Filament\Resources\CannedResponses\Schemas;

use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CannedResponseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('scope_type')
                    ->label('Область')
                    ->options([
                        '' => 'Глобально (все источники)',
                        'source' => 'Источник',
                        'department' => 'Отдел',
                    ])
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('scope_id', null)),
                Select::make('scope_id')
                    ->label(fn (Get $get): string => match ($get('scope_type')) {
                        'department' => 'Отдел',
                        'source' => 'Источник',
                        default => 'ID',
                    })
                    ->options(function (Get $get): array {
                        return match ($get('scope_type')) {
                            'source' => SourceModel::query()->orderBy('name')->pluck('name', 'id')->all(),
                            'department' => DepartmentModel::query()->orderBy('name')->pluck('name', 'id')->all(),
                            default => [],
                        };
                    })
                    ->searchable()
                    ->visible(fn (Get $get): bool => in_array($get('scope_type'), ['source', 'department'], true))
                    ->required(fn (Get $get): bool => in_array($get('scope_type'), ['source', 'department'], true)),
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

<?php

declare(strict_types=1);

namespace App\Filament\Resources\CannedResponses;

use App\Filament\Resources\CannedResponses\Pages\CreateCannedResponse;
use App\Filament\Resources\CannedResponses\Pages\EditCannedResponse;
use App\Filament\Resources\CannedResponses\Pages\ListCannedResponses;
use App\Filament\Resources\CannedResponses\Schemas\CannedResponseForm;
use App\Filament\Resources\CannedResponses\Tables\CannedResponsesTable;
use App\Models\CannedResponse;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CannedResponseResource extends Resource
{
    protected static ?string $model = CannedResponse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Коммуникация';

    protected static ?string $navigationLabel = 'Шаблоны ответов';

    protected static ?string $modelLabel = 'Шаблон';

    protected static ?string $pluralModelLabel = 'Шаблоны ответов';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return CannedResponseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CannedResponsesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCannedResponses::route('/'),
            'create' => CreateCannedResponse::route('/create'),
            'edit' => EditCannedResponse::route('/{record}/edit'),
        ];
    }
}

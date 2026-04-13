<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuickLinks;

use App\Filament\Resources\QuickLinks\Pages\CreateQuickLink;
use App\Filament\Resources\QuickLinks\Pages\EditQuickLink;
use App\Filament\Resources\QuickLinks\Pages\ListQuickLinks;
use App\Filament\Resources\QuickLinks\Schemas\QuickLinkForm;
use App\Filament\Resources\QuickLinks\Tables\QuickLinksTable;
use App\Models\QuickLink;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class QuickLinkResource extends Resource
{
    protected static ?string $model = QuickLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'Коммуникация';

    protected static ?string $navigationLabel = 'Быстрые ссылки';

    protected static ?string $modelLabel = 'Ссылка';

    protected static ?string $pluralModelLabel = 'Быстрые ссылки';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return QuickLinkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuickLinksTable::configure($table);
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
            'index' => ListQuickLinks::route('/'),
            'create' => CreateQuickLink::route('/create'),
            'edit' => EditQuickLink::route('/{record}/edit'),
        ];
    }
}

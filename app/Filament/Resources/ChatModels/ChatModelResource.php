<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChatModels;

use App\Application\Communication\Action\AssignChatToModerator;
use App\Filament\Resources\ChatModels\Pages\CreateChatModel;
use App\Filament\Resources\ChatModels\Pages\EditChatModel;
use App\Filament\Resources\ChatModels\Pages\ListChatModels;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ChatModelResource extends Resource
{
    protected static ?string $model = ChatModel::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Коммуникации';

    protected static ?string $navigationLabel = 'Чаты';

    protected static ?string $modelLabel = 'Чат';

    protected static ?string $pluralModelLabel = 'Чаты';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $newCount = static::getModel()::where('status', 'new')->count();

        return $newCount > 0 ? 'warning' : 'gray';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('source_id')
                ->relationship('source', 'name')
                ->label('Источник')
                ->required()
                ->disabled(),
            Select::make('department_id')
                ->relationship('department', 'name')
                ->label('Отдел')
                ->required()
                ->disabled(),
            Select::make('assigned_to')
                ->relationship('assignee', 'name')
                ->label('Назначен на')
                ->searchable()
                ->preload(),
            Select::make('status')
                ->label('Статус')
                ->options([
                    'new' => 'Новый',
                    'active' => 'Активный',
                    'closed' => 'Закрыт',
                ])
                ->required(),
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
                TextColumn::make('department.name')
                    ->label('Отдел')
                    ->sortable(),
                TextColumn::make('external_user_id')
                    ->label('Внешний ID')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'warning',
                        'active' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'new' => 'Новый',
                        'active' => 'Активный',
                        'closed' => 'Закрыт',
                        default => $state,
                    }),
                TextColumn::make('assignee.name')
                    ->label('Назначен на')
                    ->placeholder('Не назначен'),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('assign')
                    ->label('Взять себе')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->action(function (Model $record): void {
                        $user = auth()->user();
                        if ($user) {
                            app(AssignChatToModerator::class)->run((int) $record->getKey(), (int) $user->getKey());
                            $record->refresh();
                        }
                    })
                    ->visible(fn (Model $record) => $record->assigned_to === null),
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
            ->emptyStateHeading('Чаты не найдены')
            ->emptyStateDescription('Пока нет ни одного чата.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChatModels::route('/'),
            'create' => CreateChatModel::route('/create'),
            'edit' => EditChatModel::route('/{record}/edit'),
        ];
    }
}

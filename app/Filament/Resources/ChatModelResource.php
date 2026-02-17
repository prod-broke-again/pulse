<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Application\Communication\Action\AssignChatToModerator;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ChatModelResource extends Resource
{
    protected static ?string $model = ChatModel::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Communication';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('source_id')->relationship('source', 'name')->required()->disabled(),
            Select::make('department_id')->relationship('department', 'name')->required()->disabled(),
            Select::make('assigned_to')
                ->relationship('assignee', 'name')
                ->searchable()
                ->preload(),
            Select::make('status')->options([
                'new' => 'New',
                'active' => 'Active',
                'closed' => 'Closed',
            ])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('source.name'),
                TextColumn::make('department.name'),
                TextColumn::make('external_user_id'),
                TextColumn::make('status'),
                TextColumn::make('assignee.name')->placeholder('Unassigned'),
            ])
            ->actions([
                Action::make('assign')
                    ->label('Assign to me')
                    ->action(function (Model $record): void {
                        $user = auth()->user();
                        if ($user) {
                            app(AssignChatToModerator::class)->run((int) $record->getKey(), (int) $user->getKey());
                            $record->refresh();
                        }
                    })
                    ->visible(fn (Model $record) => $record->assigned_to === null),
            ])
            ->defaultSort('id');
    }

    public static function getRelations(): array
    {
        return [];
    }
}

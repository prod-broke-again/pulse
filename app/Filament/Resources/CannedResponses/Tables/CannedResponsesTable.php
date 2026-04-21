<?php

declare(strict_types=1);

namespace App\Filament\Resources\CannedResponses\Tables;

use App\Models\CannedResponse;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CannedResponsesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scope_label')
                    ->label('Область')
                    ->state(function (CannedResponse $record): string {
                        if ($record->scope_type === null && $record->scope_id === null) {
                            return 'Глобально';
                        }
                        if ($record->scope_type === 'source') {
                            return 'Источник #'.$record->scope_id;
                        }
                        if ($record->scope_type === 'department') {
                            return 'Отдел #'.$record->scope_id;
                        }

                        return '—';
                    })
                    ->searchable(false),
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

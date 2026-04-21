<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuickLinks\Tables;

use App\Models\QuickLink;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuickLinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scope_label')
                    ->label('Область')
                    ->state(function (QuickLink $record): string {
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
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('url')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
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

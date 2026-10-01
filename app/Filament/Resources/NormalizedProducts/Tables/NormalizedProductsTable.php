<?php

namespace App\Filament\Resources\NormalizedProducts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NormalizedProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('normalized_name')
                    ->label('Canonical Key')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('parsed_name')
                    ->label('Nama Obat')
                    ->searchable(),
                TextColumn::make('parsed_strength')
                    ->label('Dosis / Kekuatan')
                    ->searchable(),
                TextColumn::make('parsed_form')
                    ->label('Bentuk Sediaan')
                    ->searchable(),
                TextColumn::make('lowest_price')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('supplier.company_name')
                    ->label('Sales / PT Termurah')
                    ->default(fn ($record) => $record->supplier ? ($record->supplier->company_name ?: $record->supplier->name) : '-')
                    ->sortable()
                    ->searchable(['name', 'company_name']),
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

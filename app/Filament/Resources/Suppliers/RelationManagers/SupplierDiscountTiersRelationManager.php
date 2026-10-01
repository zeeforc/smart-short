<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupplierDiscountTiersRelationManager extends RelationManager
{
    protected static string $relationship = 'supplierDiscountTiers';

    protected static ?string $title = 'Aturan Diskon Global (Semua Obat)';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('min_qty')
                    ->label('Minimal QTY')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Pembelian minimal untuk mendapatkan diskon ini'),
                TextInput::make('discount_pct')
                    ->label('Diskon (%)')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(100)
                    ->step('0.01')
                    ->suffix('%'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('min_qty')
            ->columns([
                TextColumn::make('min_qty')
                    ->label('Minimal QTY')
                    ->sortable(),
                TextColumn::make('discount_pct')
                    ->label('Diskon')
                    ->suffix('%')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('min_qty', 'asc');
    }
}

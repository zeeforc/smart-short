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

class ProductDiscountTiersRelationManager extends RelationManager
{
    protected static string $relationship = 'productDiscountTiers';

    protected static ?string $title = 'Aturan Diskon Khusus Obat (Pengecualian)';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('product_name')
                    ->label('Nama/Kode Obat')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Masukkan nama obat secara spesifik untuk diskon ini (contoh: Paracetamol 500mg)'),
                TextInput::make('min_qty')
                    ->label('Minimal QTY')
                    ->required()
                    ->numeric()
                    ->minValue(1),
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
            ->recordTitleAttribute('product_name')
            ->columns([
                TextColumn::make('product_name')
                    ->label('Nama Obat')
                    ->searchable()
                    ->sortable(),
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
            ->defaultSort('product_name', 'asc');
    }
}

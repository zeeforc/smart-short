<?php

namespace App\Filament\Resources\NormalizedProducts;

use App\Filament\Resources\NormalizedProducts\Pages\CreateNormalizedProduct;
use App\Filament\Resources\NormalizedProducts\Pages\EditNormalizedProduct;
use App\Filament\Resources\NormalizedProducts\Pages\ListNormalizedProducts;
use App\Filament\Resources\NormalizedProducts\Schemas\NormalizedProductForm;
use App\Filament\Resources\NormalizedProducts\Tables\NormalizedProductsTable;
use App\Models\NormalizedProduct;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NormalizedProductResource extends Resource
{
    protected static ?string $model = NormalizedProduct::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Laporan Sortir';
    protected static ?string $navigationLabel = 'Harga Terbaik';
    protected static ?string $modelLabel = 'Harga Terbaik';
    protected static ?string $pluralModelLabel = 'Daftar Harga Terbaik';
    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $recordTitleAttribute = 'normalized_name';

    public static function form(Schema $schema): Schema
    {
        return NormalizedProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NormalizedProductsTable::configure($table);
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
            'index' => ListNormalizedProducts::route('/'),
            'create' => CreateNormalizedProduct::route('/create'),
            'edit' => EditNormalizedProduct::route('/{record}/edit'),
        ];
    }
}

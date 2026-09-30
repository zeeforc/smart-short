<?php

namespace App\Filament\Resources\RawProducts;

use App\Filament\Resources\RawProducts\Pages\CreateRawProduct;
use App\Filament\Resources\RawProducts\Pages\EditRawProduct;
use App\Filament\Resources\RawProducts\Pages\ListRawProducts;
use App\Filament\Resources\RawProducts\Schemas\RawProductForm;
use App\Filament\Resources\RawProducts\Tables\RawProductsTable;
use App\Models\RawProduct;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RawProductResource extends Resource
{
    protected static ?string $model = RawProduct::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'Data Mentah (Cache)';
    protected static ?string $modelLabel = 'Data Mentah';
    protected static ?string $pluralModelLabel = 'Data Mentah (Sementara)';
    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $recordTitleAttribute = 'raw_name';

    public static function form(Schema $schema): Schema
    {
        return RawProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RawProductsTable::configure($table);
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
            'index' => ListRawProducts::route('/'),
            'create' => CreateRawProduct::route('/create'),
            'edit' => EditRawProduct::route('/{record}/edit'),
        ];
    }
}

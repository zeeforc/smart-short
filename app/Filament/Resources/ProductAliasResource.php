<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductAliasResource\Pages;
use App\Models\ProductAlias;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class ProductAliasResource extends Resource
{
    protected static ?string $model = ProductAlias::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrows-right-left';
    
    protected static ?string $navigationLabel = 'Alias & Jodohkan Obat';
    
    protected static \UnitEnum|string|null $navigationGroup = 'Master Data';
    
    protected static ?string $pluralModelLabel = 'Alias Obat';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('alias_raw')
                    ->label('Nama Ketikan Obat (Raw)')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('alias_normalized')
                    ->label('Nama Terekstrak (Normalized)')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('normalized_product_id')
                    ->label('Produk Asli di Sistem')
                    ->relationship('normalizedProduct', 'parsed_name')
                    ->searchable()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('alias_raw')
                    ->label('Ketikan Defecta')
                    ->searchable(),
                Tables\Columns\TextColumn::make('alias_normalized')
                    ->label('Token Bersih')
                    ->searchable(),
                Tables\Columns\TextColumn::make('normalizedProduct.parsed_name')
                    ->label('Dijodohkan Ke')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProductAliases::route('/'),
        ];
    }
}

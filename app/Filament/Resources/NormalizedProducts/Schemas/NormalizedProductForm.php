<?php

namespace App\Filament\Resources\NormalizedProducts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class NormalizedProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('normalized_name')
                    ->required(),
                TextInput::make('lowest_price')
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('$'),
                TextInput::make('best_supplier_id')
                    ->numeric(),
                TextInput::make('price_history_json'),
            ]);
    }
}

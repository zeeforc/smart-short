<?php

namespace App\Filament\Resources\RawProducts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RawProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('upload_file_id')
                    ->required()
                    ->numeric(),
                TextInput::make('supplier_id')
                    ->required()
                    ->numeric(),
                TextInput::make('raw_name')
                    ->required(),
                TextInput::make('raw_unit'),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('discount')
                    ->required()
                    ->numeric()
                    ->default(0.0),
            ]);
    }
}

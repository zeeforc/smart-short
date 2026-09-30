<?php

namespace App\Filament\Resources\UploadFiles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UploadFileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('upload_batch_id')
                    ->required()
                    ->numeric(),
                TextInput::make('supplier_id')
                    ->numeric(),
                TextInput::make('file_path')
                    ->required(),
                TextInput::make('total_rows')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('status')
                    ->options(['pending' => 'Pending', 'parsing' => 'Parsing', 'matched' => 'Matched', 'failed' => 'Failed'])
                    ->default('pending')
                    ->required(),
            ]);
    }
}

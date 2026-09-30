<?php

namespace App\Filament\Resources\UploadBatches\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UploadBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('batch_code')
                    ->required(),
                TextInput::make('uploaded_by')
                    ->required()
                    ->numeric(),
                Select::make('status')
                    ->options(['processing' => 'Processing', 'completed' => 'Completed', 'failed' => 'Failed'])
                    ->default('processing')
                    ->required(),
            ]);
    }
}

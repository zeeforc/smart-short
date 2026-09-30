<?php

namespace App\Filament\Resources\RawProducts\Pages;

use App\Filament\Resources\RawProducts\RawProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRawProduct extends EditRecord
{
    protected static string $resource = RawProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

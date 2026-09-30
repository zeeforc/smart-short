<?php

namespace App\Filament\Resources\RawProducts\Pages;

use App\Filament\Resources\RawProducts\RawProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRawProducts extends ListRecords
{
    protected static string $resource = RawProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

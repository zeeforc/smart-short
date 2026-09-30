<?php

namespace App\Filament\Resources\NormalizedProducts\Pages;

use App\Filament\Resources\NormalizedProducts\NormalizedProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNormalizedProduct extends EditRecord
{
    protected static string $resource = NormalizedProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\ProductAliasResource\Pages;

use App\Filament\Resources\ProductAliasResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageProductAliases extends ManageRecords
{
    protected static string $resource = ProductAliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Alias'),
        ];
    }
}

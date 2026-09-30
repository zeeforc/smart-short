<?php

namespace App\Filament\Resources\UploadFiles\Pages;

use App\Filament\Resources\UploadFiles\UploadFileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUploadFiles extends ListRecords
{
    protected static string $resource = UploadFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

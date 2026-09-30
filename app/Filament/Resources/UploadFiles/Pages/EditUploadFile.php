<?php

namespace App\Filament\Resources\UploadFiles\Pages;

use App\Filament\Resources\UploadFiles\UploadFileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUploadFile extends EditRecord
{
    protected static string $resource = UploadFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

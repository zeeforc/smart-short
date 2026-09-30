<?php

namespace App\Filament\Resources\UploadBatches\Pages;

use App\Filament\Resources\UploadBatches\UploadBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUploadBatch extends EditRecord
{
    protected static string $resource = UploadBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

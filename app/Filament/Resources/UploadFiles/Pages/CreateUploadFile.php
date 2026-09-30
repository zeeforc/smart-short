<?php

namespace App\Filament\Resources\UploadFiles\Pages;

use App\Filament\Resources\UploadFiles\UploadFileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUploadFile extends CreateRecord
{
    protected static string $resource = UploadFileResource::class;
}

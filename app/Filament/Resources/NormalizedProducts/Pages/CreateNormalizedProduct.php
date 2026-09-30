<?php

namespace App\Filament\Resources\NormalizedProducts\Pages;

use App\Filament\Resources\NormalizedProducts\NormalizedProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNormalizedProduct extends CreateRecord
{
    protected static string $resource = NormalizedProductResource::class;
}

<?php

namespace App\Filament\Resources\UploadBatches;

use App\Filament\Resources\UploadBatches\Pages\CreateUploadBatch;
use App\Filament\Resources\UploadBatches\Pages\EditUploadBatch;
use App\Filament\Resources\UploadBatches\Pages\ListUploadBatches;
use App\Filament\Resources\UploadBatches\Schemas\UploadBatchForm;
use App\Filament\Resources\UploadBatches\Tables\UploadBatchesTable;
use App\Models\UploadBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UploadBatchResource extends Resource
{
    protected static ?string $model = UploadBatch::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Impor & Riwayat';

    protected static ?string $navigationLabel = 'Upload Pricelist';

    protected static ?string $modelLabel = 'Sesi Upload';

    protected static ?string $pluralModelLabel = 'Upload Pricelist Baru';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowUp;

    protected static ?string $recordTitleAttribute = 'batch_code';

    public static function form(Schema $schema): Schema
    {
        return UploadBatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UploadBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUploadBatches::route('/'),
            'create' => CreateUploadBatch::route('/create'),
            'edit' => EditUploadBatch::route('/{record}/edit'),
        ];
    }
}

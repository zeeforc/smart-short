<?php

namespace App\Filament\Resources\UploadFiles;

use App\Filament\Resources\UploadFiles\Pages\CreateUploadFile;
use App\Filament\Resources\UploadFiles\Pages\EditUploadFile;
use App\Filament\Resources\UploadFiles\Pages\ListUploadFiles;
use App\Filament\Resources\UploadFiles\Schemas\UploadFileForm;
use App\Filament\Resources\UploadFiles\Tables\UploadFilesTable;
use App\Models\UploadFile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UploadFileResource extends Resource
{
    protected static ?string $model = UploadFile::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Impor & Riwayat';

    protected static ?string $navigationLabel = 'Status Antrean';

    protected static ?string $modelLabel = 'File Excel';

    protected static ?string $pluralModelLabel = 'Status Antrean File';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $recordTitleAttribute = 'file_path';

    public static function form(Schema $schema): Schema
    {
        return UploadFileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UploadFilesTable::configure($table);
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
            'index' => ListUploadFiles::route('/'),
            'create' => CreateUploadFile::route('/create'),
            'edit' => EditUploadFile::route('/{record}/edit'),
        ];
    }
}

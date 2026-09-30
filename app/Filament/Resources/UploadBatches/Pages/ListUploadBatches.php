<?php

namespace App\Filament\Resources\UploadBatches\Pages;

use App\Filament\Resources\UploadBatches\UploadBatchResource;
use App\Models\UploadBatch;
use App\Models\UploadFile;
use App\Models\Supplier;
use App\Imports\RawProductImport;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListUploadBatches extends ListRecords
{
    protected static string $resource = UploadBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Upload File Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\Repeater::make('uploads')
                        ->label('Daftar File Excel')
                        ->schema([
                            \Filament\Schemas\Components\Section::make()
                                ->schema([
                                    \Filament\Forms\Components\FileUpload::make('file_path')
                                        ->label('File Excel')
                                        ->preserveFilenames()
                                        ->acceptedFileTypes([
                                            'application/vnd.ms-excel', 
                                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 
                                            'text/csv',
                                            'application/pdf'
                                        ])
                                        ->required(),
                                    \Filament\Forms\Components\TextInput::make('global_discount')
                                        ->label('Diskon Keseluruhan (Global)')
                                        ->placeholder('Contoh: 5% atau 5000')
                                        ->helperText('Diskon ini akan memotong harga semua obat di file ini otomatis.'),
                                ])
                                ->columns(1),
                        ])
                        ->addActionLabel('Tambah File Excel')
                        ->defaultItems(1)
                        ->columns(1),
                ])
                ->action(function (array $data) {
                    $batch = UploadBatch::create([
                        'batch_code' => 'BATCH-' . date('Ymd-His'),
                        'uploaded_by' => auth()->id() ?? 1, // Fallback dummy user
                        'status' => 'processing',
                    ]);

                    foreach ($data['uploads'] as $item) {
                        // Extract filename to be passed to HeaderFinderImport
                        $originalFileName = pathinfo($item['file_path'], PATHINFO_FILENAME);
                        $filePath = $item['file_path'];

                        if (\Illuminate\Support\Str::endsWith(strtolower($filePath), '.pdf')) {
                            $converter = new \App\Services\PdfToCsvService();
                            $fullPdfPath = \Illuminate\Support\Facades\Storage::disk('local')->path($filePath);
                            $csvFilePath = $converter->convert($fullPdfPath);
                            $filePath = $csvFilePath; // Gunakan file CSV yang baru untuk proses selanjutnya
                        }

                        // Cari tau letak baris judul dan info Supplier
                        $finder = new \App\Imports\HeaderFinderImport($originalFileName);
                        Excel::import($finder, $filePath, 'local');
                        
                        $headerRow = $finder->headerRow;
                        $supplierName = $finder->supplierName;

                        // Jika tidak terdeteksi dari nama file maupun isi file, fallback ke nama file asli
                        if (!$supplierName) {
                            $supplierName = strtoupper($originalFileName);
                        }

                        // Cari atau buat Supplier baru secara otomatis dengan info lengkap
                        $supplier = Supplier::firstOrCreate(
                            ['name' => $supplierName],
                            [
                                'company_name' => $finder->companyName,
                                'contact_person' => $finder->contactPerson,
                                'phone' => $finder->phone,
                                'is_active' => true,
                            ]
                        );

                        // Update datanya kalau ada info baru yang ketemu di Excel (tapi jangan override jadi kosong)
                        if ($finder->companyName && !$supplier->company_name) $supplier->company_name = $finder->companyName;
                        if ($finder->contactPerson && !$supplier->contact_person) $supplier->contact_person = $finder->contactPerson;
                        if ($finder->phone && !$supplier->phone) $supplier->phone = $finder->phone;
                        $supplier->save();

                        $uploadFile = UploadFile::create([
                            'upload_batch_id' => $batch->id,
                            'supplier_id' => $supplier->id, 
                            'file_path' => $filePath,
                            'status' => 'pending',
                        ]);

                        // Lempar ke Background Job (Queue) dengan info baris judul, supplier, dan diskon
                        Excel::queueImport(new RawProductImport($uploadFile->id, $uploadFile->supplier_id, $headerRow, $item['global_discount'] ?? null), $filePath, 'local');
                    }
                })
                ->successNotificationTitle('Upload berhasil! File sedang diproses di background.'),
        ];
    }
}

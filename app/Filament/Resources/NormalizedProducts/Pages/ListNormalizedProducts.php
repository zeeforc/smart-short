<?php

namespace App\Filament\Resources\NormalizedProducts\Pages;

use App\Exports\NormalizedProductsExport;
use App\Filament\Resources\NormalizedProducts\NormalizedProductResource;
use App\Models\NormalizedProduct;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListNormalizedProducts extends ListRecords
{
    protected static string $resource = NormalizedProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Download Excel Hasil Sortir')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    Select::make('download_mode')
                        ->label('Mode Download')
                        ->options([
                            'all_combined' => 'Satu File Gabungan (Semua PT)',
                            'all_zipped' => 'Pisah per PT (Download ZIP)',
                            'specific' => 'Pilih PT Spesifik',
                        ])
                        ->default('all_combined')
                        ->live(),

                    Select::make('supplier_id')
                        ->label('Pilih Sales / PT Termurah')
                        ->options(Supplier::pluck('name', 'id'))
                        ->searchable()
                        ->visible(fn ($get) => $get('download_mode') === 'specific')
                        ->required(fn ($get) => $get('download_mode') === 'specific'),
                ])
                ->action(function (array $data) {
                    $mode = $data['download_mode'] ?? 'all_combined';

                    if ($mode === 'all_zipped') {
                        $zipFileName = 'Hasil_Sortir_Semua_PT_'.date('Y-m-d_H-i-s').'.zip';
                        $zipPath = storage_path('app/'.$zipFileName);

                        $zip = new \ZipArchive;
                        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                            $suppliers = Supplier::all();
                            $filesToDelete = [];

                            foreach ($suppliers as $supplier) {
                                // Cek apakah supplier ini punya produk yang dimenangkan
                                if (NormalizedProduct::where('best_supplier_id', $supplier->id)->exists()) {
                                    $safeName = preg_replace('/[^A-Za-z0-9\-]/', '_', $supplier->name);
                                    $excelName = 'Hasil_Sortir_'.$safeName.'.xlsx';
                                    $tempPath = 'temp_excel/'.$excelName;

                                    // Generate file Excel dan simpan ke disk local
                                    Excel::store(new NormalizedProductsExport($supplier->id), $tempPath, 'local');

                                    $absoluteTempPath = Storage::disk('local')->path($tempPath);
                                    $zip->addFile($absoluteTempPath, $excelName);
                                    $filesToDelete[] = $absoluteTempPath;
                                }
                            }
                            $zip->close();

                            // Bersihkan file Excel satuan (yang sementara) setelah masuk ZIP
                            foreach ($filesToDelete as $file) {
                                if (file_exists($file)) {
                                    @unlink($file);
                                }
                            }

                            // Kirim file ZIP ke user dan hapus file ZIP setelah terkirim
                            return response()->download($zipPath)->deleteFileAfterSend(true);
                        }

                        Notification::make()
                            ->title('Gagal membuat ZIP')
                            ->danger()
                            ->send();

                        return null;
                    }

                    $supplierId = $mode === 'specific' ? ($data['supplier_id'] ?? null) : null;
                    $filename = 'Hasil_Sortir_Gabungan_'.date('Y-m-d').'.xlsx';

                    if ($supplierId) {
                        $supplier = Supplier::find($supplierId);
                        if ($supplier) {
                            $safeName = preg_replace('/[^A-Za-z0-9\-]/', '_', $supplier->name);
                            $filename = 'Hasil_Sortir_'.$safeName.'_'.date('Y-m-d').'.xlsx';
                        }
                    }

                    return Excel::download(new NormalizedProductsExport($supplierId), $filename);
                }),
            CreateAction::make(),
        ];
    }
}

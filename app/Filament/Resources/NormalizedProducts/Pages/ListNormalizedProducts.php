<?php

namespace App\Filament\Resources\NormalizedProducts\Pages;

use App\Filament\Resources\NormalizedProducts\NormalizedProductResource;
use App\Exports\NormalizedProductsExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
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
                    \Filament\Forms\Components\Select::make('download_mode')
                        ->label('Mode Download')
                        ->options([
                            'all_combined' => 'Satu File Gabungan (Semua PT)',
                            'all_zipped' => 'Pisah per PT (Download ZIP)',
                            'specific' => 'Pilih PT Spesifik',
                        ])
                        ->default('all_combined')
                        ->live(),
                        
                    \Filament\Forms\Components\Select::make('supplier_id')
                        ->label('Pilih Sales / PT Termurah')
                        ->options(\App\Models\Supplier::pluck('name', 'id'))
                        ->searchable()
                        ->visible(fn ($get) => $get('download_mode') === 'specific')
                        ->required(fn ($get) => $get('download_mode') === 'specific'),
                ])
                ->action(function (array $data) {
                    $mode = $data['download_mode'] ?? 'all_combined';
                    
                    if ($mode === 'all_zipped') {
                        $zipFileName = 'Hasil_Sortir_Semua_PT_' . date('Y-m-d_H-i-s') . '.zip';
                        $zipPath = storage_path('app/' . $zipFileName);
                        
                        $zip = new \ZipArchive();
                        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                            $suppliers = \App\Models\Supplier::all();
                            $filesToDelete = [];
                            
                            foreach ($suppliers as $supplier) {
                                // Cek apakah supplier ini punya produk yang dimenangkan
                                if (\App\Models\NormalizedProduct::where('best_supplier_id', $supplier->id)->exists()) {
                                    $safeName = preg_replace('/[^A-Za-z0-9\-]/', '_', $supplier->name);
                                    $excelName = 'Hasil_Sortir_' . $safeName . '.xlsx';
                                    $tempPath = 'temp_excel/' . $excelName;
                                    
                                    // Generate file Excel dan simpan ke disk local
                                    \Maatwebsite\Excel\Facades\Excel::store(new \App\Exports\NormalizedProductsExport($supplier->id), $tempPath, 'local');
                                    
                                    $absoluteTempPath = \Illuminate\Support\Facades\Storage::disk('local')->path($tempPath);
                                    $zip->addFile($absoluteTempPath, $excelName);
                                    $filesToDelete[] = $absoluteTempPath;
                                }
                            }
                            $zip->close();
                            
                            // Bersihkan file Excel satuan (yang sementara) setelah masuk ZIP
                            foreach ($filesToDelete as $file) {
                                if (file_exists($file)) @unlink($file);
                            }
                            
                            // Kirim file ZIP ke user dan hapus file ZIP setelah terkirim
                            return response()->download($zipPath)->deleteFileAfterSend(true);
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Gagal membuat ZIP')
                            ->danger()
                            ->send();
                        return null;
                    }
                    
                    $supplierId = $mode === 'specific' ? ($data['supplier_id'] ?? null) : null;
                    $filename = 'Hasil_Sortir_Gabungan_' . date('Y-m-d') . '.xlsx';
                    
                    if ($supplierId) {
                        $supplier = \App\Models\Supplier::find($supplierId);
                        if ($supplier) {
                            $safeName = preg_replace('/[^A-Za-z0-9\-]/', '_', $supplier->name);
                            $filename = 'Hasil_Sortir_' . $safeName . '_' . date('Y-m-d') . '.xlsx';
                        }
                    }
                    
                    return Excel::download(new NormalizedProductsExport($supplierId), $filename);
                }),
            CreateAction::make(),
        ];
    }
}

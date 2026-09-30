<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Supplier;
use App\Models\UploadBatch;
use App\Models\UploadFile;
use App\Models\RawProduct;
use App\Models\NormalizedProduct;
use App\Imports\RawProductImport;
use App\Services\ProductNormalizationService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class TestImportCommand extends Command
{
    protected $signature = 'test:import';
    protected $description = 'Simulasi upload 2 file excel dan tes fuzzy matching';

    public function handle(ProductNormalizationService $normalizer)
    {
        $this->info('🚀 Memulai Simulasi Data Sales...');

        // 1. Bersihkan Data Lama
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        RawProduct::truncate();
        NormalizedProduct::truncate();
        UploadFile::truncate();
        UploadBatch::truncate();
        Supplier::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1.5 Bikin User Dummy
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'admin@test.com'],
            ['name' => 'Admin Test', 'password' => bcrypt('password')]
        );

        // 2. Bikin 2 Supplier Dummy
        $supplierA = Supplier::create(['name' => 'Sales A (PT. Farma Setia)', 'company_name' => 'PT Farma Setia']);
        $supplierB = Supplier::create(['name' => 'Sales B (PT. Maju Sehat)', 'company_name' => 'PT Maju Sehat']);

        // 3. Bikin File CSV (Simulasi File Excel)
        // Perhatikan bahwa Sales A dan Sales B pakai format nama kolom & penamaan obat yang BEDA
        $csvA = "Nama Obat,Satuan,Harga\nAmox 500mg (Box),Box,Rp 15.000\nParacetamol 500 mg,Strip,3.500\nVitamin C IPI,Botol,5000\n";
        $csvB = "Produk,Unit,Price\nAmoxicillin 500 mg,Boks,14000\nParacetamol 500mg (10 Strip),Strp,4.000\nBodrex Extra,Tablet,Rp 2.000\n";

        Storage::disk('local')->put('dummy_sales_a.csv', $csvA);
        Storage::disk('local')->put('dummy_sales_b.csv', $csvB);

        // 4. Bikin Batch & File
        $batch = UploadBatch::create(['batch_code' => 'BATCH-TEST', 'uploaded_by' => $user->id]);
        $fileA = UploadFile::create(['upload_batch_id' => $batch->id, 'supplier_id' => $supplierA->id, 'file_path' => 'dummy_sales_a.csv']);
        $fileB = UploadFile::create(['upload_batch_id' => $batch->id, 'supplier_id' => $supplierB->id, 'file_path' => 'dummy_sales_b.csv']);

        // 5. Import Excel ke RawProducts
        $this->info('📥 Meng-import 2 File Excel ke database mentah (Queued)...');
        Excel::import(new RawProductImport($fileA->id, $fileA->supplier_id), 'dummy_sales_a.csv', 'local');
        Excel::import(new RawProductImport($fileB->id, $fileB->supplier_id), 'dummy_sales_b.csv', 'local');

        // Jalankan Queue Worker sebentar untuk memproses import di background
        $this->info('⏳ Menjalankan Queue Worker untuk memproses import...');
        \Illuminate\Support\Facades\Artisan::call('queue:work', ['--stop-when-empty' => true]);

        // 6. Jalankan Service Fuzzy Matching
        $this->info('🧠 Menjalankan Algoritma Fuzzy Matching...');
        $normalizer->processBatch($batch->id);

        $this->info('✅ Selesai! Berikut hasil sortir & adu harga termurahnya:');

        // 7. Tampilkan Hasil di Terminal
        $normalized = NormalizedProduct::all();
        $headers = ['Nama Bersih (Normalized)', 'Harga Termurah', 'Supplier Termurah', 'Total Sales yg Jual'];
        $data = [];
        
        foreach ($normalized as $p) {
            $history = is_array($p->price_history_json) ? $p->price_history_json : json_decode($p->price_history_json, true) ?? [];
            $supplierName = Supplier::find($p->best_supplier_id)->name ?? '-';
            
            $data[] = [
                $p->normalized_name,
                "Rp " . number_format($p->lowest_price, 0, ',', '.'),
                $supplierName,
                count($history) . ' Sales'
            ];
        }

        $this->table($headers, $data);
    }
}

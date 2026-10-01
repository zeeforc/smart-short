<?php

namespace App\Imports;

use App\Models\RawProduct;
use App\Models\UploadBatch;
use App\Models\UploadFile;
use App\Services\ProductNormalizationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterChunk;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;

class RawProductImport implements ShouldQueue, ToModel, WithBatchInserts, WithChunkReading, WithEvents, WithHeadingRow
{
    public $upload_file_id;

    public $supplier_id;

    public $header_row;

    public $global_discount;

    public function __construct($upload_file_id, $supplier_id, $header_row = 1, $global_discount = null)
    {
        $this->upload_file_id = $upload_file_id;
        $this->supplier_id = $supplier_id;
        $this->header_row = $header_row;
        $this->global_discount = $global_discount;
    }

    public function headingRow(): int
    {
        return $this->header_row;
    }

    public function model(array $row): Model|array|null
    {
        // Deteksi kolom secara dinamis
        $name = $this->findValue($row, ['nama', 'obat', 'produk', 'item', 'name', 'deskripsi']);
        $unit = $this->findValue($row, ['satuan', 'unit', 'kemasan', 'box', 'kemas', 'bentuk']);

        // Prioritaskan mencari Harga Grosir dulu, kalau nggak ketemu baru cari Harga biasa
        $price = $this->findValue($row, ['grosir']);
        if (! $price) {
            $price = $this->findValue($row, ['harga', 'price', 'hrg', 'modal', 'hpp', 'hna']);
        }

        $discount = $this->findValue($row, ['diskon', 'discount', 'disc', 'potongan']);

        $cleanedPrice = $this->cleanNumber($price);
        $lowerName = strtolower(trim(strval($name)));

        // 1. Tolak jika nama sama dengan nama header
        if (in_array($lowerName, ['nama obat', 'nama produk', 'nama item', 'nama', 'obat', 'produk', 'deskripsi', 'nama obat / produk'])) {
            return null;
        }

        // 2. Tolak jika nama tidak punya huruf sama sekali (hanya angka/simbol)
        if (! preg_match('/[a-zA-Z]/', strval($name))) {
            return null;
        }

        // 3. Skip jika baris kosong, harga tidak valid, atau kepanjangan
        if (empty($name) || $cleanedPrice <= 0 || strlen(strval($name)) > 150) {
            return null;
        }

        // Hitung diskon yang tertulis di Excel (per baris)
        $rowDiscount = $this->cleanNumber($discount ?? 0);

        // Hitung diskon global jika diinput oleh user
        $calculatedGlobalDiscount = 0;
        if (! empty($this->global_discount)) {
            $gd = strtolower(trim(strval($this->global_discount)));
            if (str_contains($gd, '%')) {
                // Diskon persentase, e.g., "5%"
                $percentage = (float) str_replace('%', '', $gd);
                $calculatedGlobalDiscount = $cleanedPrice * ($percentage / 100);
            } else {
                // Diskon nominal fix, e.g., "5000"
                $calculatedGlobalDiscount = (float) preg_replace('/[^\d.,]/', '', $gd);
            }
        }

        // Total diskon yang diberikan (di file + global)
        $totalDiscount = $rowDiscount + $calculatedGlobalDiscount;

        // Kurangi harga asli dengan total diskon
        // Pastikan harga akhir tidak jadi minus (minimal 0)
        $finalPrice = max(0, $cleanedPrice - $totalDiscount);

        return new RawProduct([
            'upload_file_id' => $this->upload_file_id,
            'supplier_id' => $this->supplier_id,
            'raw_name' => $name,
            'raw_unit' => $unit ?? '-',
            'price' => $finalPrice,
            'discount' => $totalDiscount,
        ]);
    }

    /**
     * Cari value berdasarkan keyword kemiripan nama kolom (header)
     */
    private function findValue(array $row, array $keywords)
    {
        foreach ($row as $key => $value) {
            foreach ($keywords as $keyword) {
                if (stripos(strval($key), $keyword) !== false) {
                    return $value;
                }
            }
        }

        return null; // Return null jika nggak ketemu satupun
    }

    /**
     * Bersihkan format uang/angka (misal: "Rp 15.000,00", "10,500", "35,963.00")
     */
    private function cleanNumber($value): float
    {
        if (empty($value)) {
            return 0.0;
        }

        $clean = trim((string) $value);
        $clean = preg_replace('/[^\d.,]/', '', $clean);

        if ($clean === '') {
            return 0.0;
        }

        $hasDot = str_contains($clean, '.');
        $hasComma = str_contains($clean, ',');

        if ($hasDot && $hasComma) {
            $lastDot = strrpos($clean, '.');
            $lastComma = strrpos($clean, ',');
            if ($lastDot > $lastComma) {
                // US standard: 12,000.50 -> strip comma
                $clean = str_replace(',', '', $clean);
            } else {
                // ID standard: 12.000,50 -> strip dot, comma to dot
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            }

            return (float) $clean;
        }

        if ($hasComma) {
            // Check if comma is thousand separator (e.g. 10,500 or 1,250,000)
            if (preg_match('/,(\d{3})(?:$|,)/', $clean)) {
                $clean = str_replace(',', '', $clean);
            } else {
                $clean = str_replace(',', '.', $clean);
            }

            return (float) $clean;
        }

        if ($hasDot) {
            // Check if dot is decimal (e.g. 23310.00 or 150.50)
            if (preg_match('/\.\d{2}$/', $clean)) {
                return (float) $clean;
            }

            // Check if dot is thousand separator (e.g. 11.000 or 1.500.000)
            if (preg_match('/\.\d{3}(?:\.|$)/', $clean)) {
                $clean = str_replace('.', '', $clean);

                return (float) $clean;
            }
        }

        return (float) $clean;
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                // Ambil total baris dari file Excel
                $totalRows = $event->getReader()->getTotalRows();
                $total = is_array($totalRows) ? (array_values($totalRows)[0] ?? 0) : 0;

                $file = UploadFile::find($this->upload_file_id);
                if ($file) {
                    $file->update([
                        'total_rows' => $total,
                        'processed_rows' => 0,
                        'status' => 'parsing',
                    ]);
                }
            },
            AfterChunk::class => function (AfterChunk $event) {
                // Increment processed_rows setiap kali 1 chunk selesai diinsert
                DB::table('upload_files')
                    ->where('id', $this->upload_file_id)
                    ->increment('processed_rows', $this->chunkSize());
            },
            AfterImport::class => function (AfterImport $event) {
                // Tandai file ini sudah selesai di-import
                $file = UploadFile::find($this->upload_file_id);
                if ($file) {
                    // Set processed_rows sama dengan total_rows biar 100% pas selesai
                    $file->update([
                        'status' => 'matched',
                        'processed_rows' => $file->total_rows,
                    ]);

                    // Cek apakah SEMUA file dalam batch ini sudah selesai?
                    $batch = UploadBatch::find($file->upload_batch_id);
                    $pendingCount = UploadFile::where('upload_batch_id', $batch->id)
                        ->where('status', '!=', 'matched')
                        ->count();

                    // Kalau semuanya beres, tandai Batch selesai dan jalankan Algoritma Fuzzy Matching
                    if ($pendingCount === 0) {
                        $batch->update(['status' => 'completed']);
                        app(ProductNormalizationService::class)->processBatch($batch->id);
                    }
                }
            },
        ];
    }
}

<?php

namespace App\Imports;

use App\Models\RawProduct;
use App\Models\Supplier;
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

    public $mapping = [];

    public function __construct($upload_file_id, $supplier_id, $header_row = 1, $global_discount = null)
    {
        $this->upload_file_id = $upload_file_id;
        $this->supplier_id = $supplier_id;
        $this->header_row = $header_row;
        $this->global_discount = $global_discount;

        $supplier = Supplier::find($supplier_id);
        $this->mapping = $supplier ? ($supplier->column_mapping ?? []) : [];
    }

    public function headingRow(): int
    {
        return $this->header_row;
    }

    public function model(array $row): Model|array|null
    {
        // 0. Siapkan keyword pencarian kolom (bisa dari setting supplier atau default)
        $nameKw = ! empty($this->mapping['name_keywords']) ? array_map('trim', explode(',', $this->mapping['name_keywords'])) : ['nama', 'obat', 'produk', 'item', 'name', 'deskripsi'];
        $unitKw = ! empty($this->mapping['unit_keywords']) ? array_map('trim', explode(',', $this->mapping['unit_keywords'])) : ['satuan', 'unit', 'kemasan', 'box', 'kemas', 'bentuk'];
        $priceKw = ! empty($this->mapping['price_keywords']) ? array_map('trim', explode(',', $this->mapping['price_keywords'])) : ['grosir', 'harga', 'price', 'hrg', 'modal', 'hpp', 'hna'];
        $discKw = ! empty($this->mapping['discount_keywords']) ? array_map('trim', explode(',', $this->mapping['discount_keywords'])) : ['diskon', 'discount', 'disc', 'potongan'];

        // Deteksi kolom secara dinamis
        $name = $this->findValue($row, $nameKw);
        $unit = $this->findValue($row, $unitKw);
        $price = $this->findValue($row, $priceKw);
        $discount = $this->findValue($row, $discKw);

        $name = trim(strval($name));

        // Bersihkan nomor urut baris di awal yang terbawa dari PDF/Excel
        // Contoh: "1465 NEBACETINE", "1507NOROID", "1. ACARBOSE", "1054) PARACETAMOL"
        $name = preg_replace('/^\d{1,5}[\.\)\-\s]+\s*/', '', $name);
        $name = preg_replace('/^\d{2,5}(?=[a-zA-Z])/', '', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));

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
        $rawDiscountStr = trim(strval($discount ?? ''));
        $rowDiscount = 0.0;
        if (! empty($rawDiscountStr)) {
            $parsedDisc = $this->cleanNumber($rawDiscountStr);
            if (str_contains($rawDiscountStr, '%') || ($parsedDisc > 0 && $parsedDisc <= 100 && $cleanedPrice > 1000)) {
                // Diskon berupa persentase (e.g. "10%" atau angka 10 pada obat berharga > Rp 1.000)
                $rowDiscount = $cleanedPrice * ($parsedDisc / 100.0);
            } else {
                // Diskon nominal rupiah
                $rowDiscount = $parsedDisc;
            }
        }

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
     * Bersihkan format uang/angka (misal: "Rp 15.000,00", "10,500", "35,963.00", "4450.545")
     */
    private function cleanNumber($value): float
    {
        if (empty($value)) {
            return 0.0;
        }

        // Jika sudah berbentuk int atau float asli dari PhpSpreadsheet, gunakan langsung
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $clean = trim((string) $value);
        $clean = preg_replace('/[^\d.,]/', '', $clean);

        if ($clean === '') {
            return 0.0;
        }

        $hasDot = str_contains($clean, '.');
        $hasComma = str_contains($clean, ',');

        // Kasus 1: Mengandung titik DAN koma (misal: "12,000.50" atau "12.000,50")
        if ($hasDot && $hasComma) {
            $lastDot = strrpos($clean, '.');
            $lastComma = strrpos($clean, ',');
            if ($lastDot > $lastComma) {
                // Format US: 12,000.50 -> hilangkan koma
                $clean = str_replace(',', '', $clean);
            } else {
                // Format ID: 12.000,50 -> hilangkan titik, ubah koma ke titik
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            }

            return (float) $clean;
        }

        // Kasus 2: Hanya mengandung koma (misal: "10,500" ribuan atau "10,50" desimal)
        if ($hasComma) {
            // Jika ada lebih dari satu koma, pasti pemisah ribuan (misal: "1,250,000")
            if (substr_count($clean, ',') > 1) {
                $clean = str_replace(',', '', $clean);

                return (float) $clean;
            }

            $parts = explode(',', $clean);
            $before = $parts[0];
            $after = $parts[1] ?? '';

            // Jika 1-3 digit di depan dan tepat 3 digit di belakang (misal: "10,500") -> ribuan
            if (strlen($before) <= 3 && strlen($after) === 3) {
                $clean = str_replace(',', '', $clean);
            } else {
                // Desimal (misal: "10,50", "4450,545") -> ubah ke titik
                $clean = str_replace(',', '.', $clean);
            }

            return (float) $clean;
        }

        // Kasus 3: Hanya mengandung titik (misal: "15.000", "4450.545", "1.500.000")
        if ($hasDot) {
            // Jika ada lebih dari satu titik, pasti pemisah ribuan (misal: "1.500.000")
            if (substr_count($clean, '.') > 1) {
                $clean = str_replace('.', '', $clean);

                return (float) $clean;
            }

            $parts = explode('.', $clean);
            $before = $parts[0];
            $after = $parts[1] ?? '';

            // JIKA DIGIT DI DEPAN TITIK > 3 (misal: "4450.545", "29761.875", "447638.025"):
            // Pemisah ribuan TIDAK PERNAH memiliki 4 digit atau lebih di depan titik tanpa separator!
            // Ini 100% adalah tanda desimal (pecahan hasil rumus Excel).
            if (strlen($before) > 3) {
                return (float) $clean;
            }

            // Jika 1-3 digit di depan dan tepat 3 digit di belakang (misal: "15.000", "250.000"):
            // Pada katalog harga obat Indonesia, ini adalah ribuan Rupiah.
            if (strlen($before) <= 3 && strlen($after) === 3) {
                $clean = str_replace('.', '', $clean);

                return (float) $clean;
            }

            // Sisanya (misal: "15.5", "15.50", "15.0000") adalah desimal.
            return (float) $clean;
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

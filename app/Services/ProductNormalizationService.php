<?php

namespace App\Services;

use App\Models\NormalizedProduct;
use App\Models\RawProduct;
use Illuminate\Support\Facades\Log;

class ProductNormalizationService
{
    /**
     * Jalankan proses normalisasi untuk semua raw produk di satu batch
     * (Biasanya dipanggil lewat Job/Command setelah semua Excel selesai di-import)
     */
    public function processBatch($uploadBatchId)
    {
        Log::info("Memulai proses normalisasi untuk Batch: {$uploadBatchId}");

        // Ambil semua raw product yang berasal dari file-file di batch ini
        $rawProducts = RawProduct::whereHas('uploadFile', function ($q) use ($uploadBatchId) {
            $q->where('upload_batch_id', $uploadBatchId);
        })->get();

        foreach ($rawProducts as $raw) {
            $this->normalizeAndCompare($raw);
        }

        Log::info("Selesai proses normalisasi untuk Batch: {$uploadBatchId}");
    }

    /**
     * Parse nama obat mentah menjadi komponen terstruktur (Canonical Key)
     */
    public function parseDrugName(string $rawName, string $rawUnit): array
    {
        $s = mb_strtolower(trim($rawName));

        // Hapus simbol bullet / minus di awal seperti "- CENDO", "* PARACETAMOL"
        $s = preg_replace('/^[-\*\•\.\s]+/', '', $s);

        // Standarisasi angka desimal koma ke titik (12,5 -> 12.5)
        $s = preg_replace('/(\d+),(\d+)/', '$1.$2', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));

        // Bersihkan tanda kurung kemasan spt (besar), (kecil), (box), (strip), (nr)
        $s = trim(preg_replace('/\([^)]*\)/', '', $s));

        // Ekstrak kekuatan/volume dosis: "500 mg", "5%", "100 mg/5 ml", "15 ml", "5 ml", "0.6 ml"
        preg_match('/(\d+(?:\.\d+)?)\s*(mg|mcg|g|iu|%|ml|l)(?:\s*\/\s*(\d+(?:\.\d+)?)\s*(ml|l))?/i', $s, $m);

        $strength = null;
        if ($m) {
            $value = rtrim(rtrim(number_format((float) $m[1], 3, '.', ''), '0'), '.');
            $unit1 = strtolower($m[2]);
            $sub = '';
            if (isset($m[4])) {
                $subVal = rtrim(rtrim(number_format((float) $m[3], 3, '.', ''), '0'), '.');
                $sub = "/{$subVal}".strtolower($m[4]);
            }
            $strength = $value.$unit1.$sub;

            // Hapus bagian kekuatan dari nama obat
            $s = str_replace($m[0], ' ', $s);
        }

        // Bersihkan kata-kata penanda kemasan / bentuk yang sering nempel di nama produk
        $noiseWords = [
            '/\b(e\.d|ed|tm|tt|tetes mata|tetes telinga)\b/i',
            '/\b(no\s*retur|noret|no\s*return|nr)\b/i',
            '/\b(strip|blister|box|botol|btl|fls|tube|pot|vial|amp)\b/i',
            '/\b(10x10|10\s*x\s*10|5x10|3x10|30x10|50x10)\b/i',
        ];
        foreach ($noiseWords as $pattern) {
            $s = preg_replace($pattern, ' ', $s);
        }

        // Hapus pemisah tanda hubung atau garis miring yang tersisa
        $s = preg_replace('/[-\/]/', ' ', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));

        // Hapus duplikasi nama brand/pabrik di akhir (misal: "cendo catarlent cendo")
        $words = explode(' ', $s);
        if (count($words) >= 3 && $words[0] === $words[count($words) - 1]) {
            array_pop($words);
            $s = implode(' ', $words);
        }

        $name = trim(preg_replace('/\s+/', ' ', $s));
        $form = mb_strtolower(trim($rawUnit));

        return [
            'name' => strtoupper($name),
            'strength' => $strength ? strtoupper($strength) : null,
            'form' => strtoupper($form),
            'canonical' => strtoupper(implode('|', [$name, $strength, $form])),
        ];
    }

    public function normalizeAndCompare(RawProduct $raw): void
    {
        $parsed = $this->parseDrugName($raw->raw_name, (string) $raw->raw_unit);

        // Normalize price to per-unit to handle suppliers that price per box/pack.
        $qtyPerPack = max(1, (int) ($raw->qty_per_pack ?? 1));
        $pricePerUnit = round($raw->price / $qtyPerPack, 4);

        $matchedProduct = NormalizedProduct::where('normalized_name', $parsed['canonical'])->first();

        if (! $matchedProduct) {
            NormalizedProduct::create([
                'normalized_name' => $parsed['canonical'],
                'parsed_name' => $parsed['name'],
                'parsed_strength' => $parsed['strength'],
                'parsed_form' => $parsed['form'],
                'lowest_price' => $pricePerUnit,
                'best_supplier_id' => $raw->supplier_id,
                'price_history_json' => [
                    [
                        'supplier_id' => $raw->supplier_id,
                        'raw_name' => $raw->raw_name,
                        'price' => $pricePerUnit,
                        'unit' => $raw->raw_unit,
                    ],
                ],
            ]);
        } else {
            $history = $matchedProduct->price_history_json ?? [];

            // Replace existing entry from the same supplier instead of appending.
            $existingIndex = null;
            foreach ($history as $idx => $entry) {
                if ((int) $entry['supplier_id'] === (int) $raw->supplier_id) {
                    $existingIndex = $idx;
                    break;
                }
            }

            $entry = [
                'supplier_id' => $raw->supplier_id,
                'raw_name' => $raw->raw_name,
                'price' => $pricePerUnit,
                'unit' => $raw->raw_unit,
            ];

            if ($existingIndex !== null) {
                $history[$existingIndex] = $entry;
            } else {
                $history[] = $entry;
            }

            $lowestPrice = min(array_column($history, 'price'));
            $bestSupplierId = collect($history)->firstWhere('price', $lowestPrice)['supplier_id'];

            $matchedProduct->update([
                'price_history_json' => $history,
                'lowest_price' => $lowestPrice,
                'best_supplier_id' => $bestSupplierId,
            ]);
        }
    }
}

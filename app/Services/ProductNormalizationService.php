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
        $s = str_replace("\u{00A0}", ' ', $rawName); // NBSP
        $s = mb_strtolower(trim($s));

        // Hapus simbol bullet / minus di awal seperti "- CENDO", "* PARACETAMOL"
        $s = preg_replace('/^[-\*\•\.\s]+/', '', $s);

        // Standarisasi angka desimal koma ke titik (12,5 -> 12.5)
        $s = preg_replace('/(\d+),(\d+)/', '$1.$2', $s);
        
        // Hapus spasi antara angka dan satuan (400 MG -> 400MG)
        $s = preg_replace('/(\d)\s*(mg|mcg|gr|gm|ml|g|iu|cc|%)\b/i', '$1$2', $s);
        
        $s = trim(preg_replace('/\s+/', ' ', $s));

        // Bersihkan tanda kurung kemasan spt (besar), (kecil), (box), (strip), (nr)
        $s = trim(preg_replace('/\([^)]*\)/', '', $s));

        // Ekstrak kekuatan/volume dosis
        preg_match('/(\d+(?:\.\d+)?)\s*(mg|mcg|g|gr|gm|iu|%|ml|l)(?:\s*\/\s*(\d+(?:\.\d+)?)\s*(ml|l))?/i', $s, $m);

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

        // Hapus pemisah tanda hubung atau garis miring yang tersisa
        $s = preg_replace('/[-\/]/', ' ', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));

        $synonyms = [
            'TABLET'=>'TAB','KAPLET'=>'KAP','KAPL'=>'KAP','KPT'=>'KAP','KAPS'=>'KAP',
            'KAPSUL'=>'KAP','CAPS'=>'KAP','CAP'=>'KAP',
            'SYRUP'=>'SYR','SIRUP'=>'SYR','SUSPENSI'=>'SYR','SUSP'=>'SYR',
            'CREAM'=>'CR','KRIM'=>'CR','SALEP'=>'OINT',
            'INJEKSI'=>'INJ','TETES'=>'DROP','DROPS'=>'DROP','TTS'=>'DROP',
            'GRAM'=>'GR','GM'=>'GR','G'=>'GR',
            'METILPREDNISOLON'=>'METHYLPREDNISOLONE',
            'ACETYLSISTEIN'=>'ACETYLCYSTEINE','ACETYLCYSTEIN'=>'ACETYLCYSTEINE',
        ];

        $noise = [
            'NORET','NORETUR','NRT','NR','RETUR','NO','NEW',
            'STRIP','BLISTER','BOX','BOTOL','BTL','FLS','TUBE','POT','VIAL','AMP',
            'E.D','ED','TM','TT',
        ];

        $words = explode(' ', $s);
        $finalWords = [];
        foreach ($words as $word) {
            $upper = strtoupper($word);
            
            // Filter noise
            if (in_array($upper, $noise, true)) {
                continue;
            }
            
            // Hapus ukuran box seperti 10x10, 5x10
            if (preg_match('/^\d+\s*x\s*\d+$/i', $word)) {
                continue;
            }
            
            // Map synonym
            if (isset($synonyms[$upper])) {
                $finalWords[] = strtolower($synonyms[$upper]);
            } else {
                $finalWords[] = $word;
            }
        }
        $s = implode(' ', $finalWords);

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

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
    public function parseDrugName(string $rawName, string $rawUnit)
    {
        $s = mb_strtolower(trim(preg_replace('/\s+/', ' ', $rawName)));
        $s = str_replace(',', '.', $s); // 12,5 -> 12.5

        // kekuatan: "500 mg", "5%", "100 mg/5 ml", "1000 iu", "0.1%"
        preg_match('/(\d+(?:\.\d+)?)\s*(mg|mcg|g|iu|%)(?:\s*\/\s*(\d+(?:\.\d+)?)\s*(ml))?/', $s, $m);

        $strength = null;
        if ($m) {
            $value = rtrim(rtrim(number_format((float) $m[1], 3, '.', ''), '0'), '.');
            $strength = $value.$m[2].(isset($m[4]) ? "/{$m[3]}{$m[4]}" : '');
        }

        // Hapus keterangan kekuatan dari nama
        $name = trim(preg_replace('/\s+/', ' ', $m ? str_replace($m[0], '', $s) : $s));

        // Bersihkan tanda kurung kemasan spt (box), (strip)
        $name = trim(preg_replace('/\([^)]+\)/', '', $name));

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

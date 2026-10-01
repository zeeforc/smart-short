<?php

namespace App\Services;

use App\Models\NormalizedProduct;
use App\Models\ProductDiscountTier;
use App\Models\Supplier;
use App\Models\SupplierDiscountTier;

class OrderCalculationService
{
    /**
     * Cari supplier termurah untuk suatu obat berdasarkan QTY
     * Termasuk memotong diskon dan menambah PPN (12%) jika belum include
     */
    public function calculateBestSupplier(string $rawProductName, int $qty): ?array
    {
        $normalizer = app(ProductNormalizationService::class);
        $parsed = $normalizer->parseDrugName($rawProductName, '');

        // Canonical from defecta input has no unit (empty string), so try matching
        // by name + strength to avoid Amlodipine 5mg matching Amlodipine 10mg.
        $product = NormalizedProduct::where('parsed_name', $parsed['name'])
            ->where('parsed_strength', $parsed['strength'])
            ->first();

        // Last resort: canonical exact match (succeeds if unit was somehow provided)
        if (! $product) {
            $product = NormalizedProduct::where('normalized_name', $parsed['canonical'])->first();
        }

        if (! $product || empty($product->price_history_json)) {
            return null;
        }

        $bestPrice = PHP_FLOAT_MAX;
        $bestSupplier = null;
        $allCalculations = [];

        // Loop semua supplier yang punya harga untuk obat ini
        foreach ($product->price_history_json as $history) {
            $supplierId = $history['supplier_id'];
            $basePrice = (float) $history['price'];
            $rawName = $history['raw_name'];

            $supplier = Supplier::find($supplierId);
            if (! $supplier || ! $supplier->is_active) {
                continue;
            }

            // 1. Cari Diskon (Cek Spesifik dulu, baru Global)
            $discountPct = $this->findDiscount($supplierId, $canonicalName, $qty);

            // 2. Potong Diskon
            $netPrice = $basePrice * (1 - ($discountPct / 100));

            // 3. Tambah PPN (12%) jika supplier belum include PPN
            $ppnRate = 0.12; // 12% PPN 2026
            $finalPrice = $supplier->is_ppn_included ? $netPrice : ($netPrice * (1 + $ppnRate));

            $result = [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'base_price' => $basePrice,
                'discount_pct' => $discountPct,
                'net_price' => $netPrice,
                'is_ppn_included' => $supplier->is_ppn_included,
                'final_price' => round($finalPrice, 2),
                'raw_name' => $rawName,
                'unit' => $history['unit'] ?? '-',
            ];

            $allCalculations[] = $result;

            if ($finalPrice < $bestPrice) {
                $bestPrice = $finalPrice;
                $bestSupplier = $result;
            }
        }

        return [
            'winner' => $bestSupplier,
            'comparisons' => collect($allCalculations)->sortBy('final_price')->values()->toArray(),
        ];
    }

    /**
     * Cari persentase diskon tertinggi yang memenuhi min_qty
     */
    private function findDiscount(int $supplierId, string $productName, int $qty): float
    {
        // Cek diskon spesifik obat
        $productDiscount = ProductDiscountTier::where('supplier_id', $supplierId)
            ->where('product_name', $productName) // Note: ini butuh fuzzy matching kalau product_name di tabel diskon nggak persis sama. Sementara kita pakai exact match.
            ->where('min_qty', '<=', $qty)
            ->orderByDesc('min_qty')
            ->first();

        if ($productDiscount) {
            return (float) $productDiscount->discount_pct;
        }

        // Kalau nggak ada, cek diskon global supplier
        $globalDiscount = SupplierDiscountTier::where('supplier_id', $supplierId)
            ->where('min_qty', '<=', $qty)
            ->orderByDesc('min_qty')
            ->first();

        if ($globalDiscount) {
            return (float) $globalDiscount->discount_pct;
        }

        return 0; // Tidak ada diskon
    }
}

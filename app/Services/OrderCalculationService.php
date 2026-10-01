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

        // 1. Cari kandidat produk yang cocok.
        // Jika kekuatan obat ada di request defecta (misal: Amlodipine 5mg), wajib cocokkan nama + kekuatan.
        // Jika defecta tidak mencantumkan kekuatan (misal: CENDO CATARLENT), cocokkan berdasarkan nama saja.
        $query = NormalizedProduct::query();
        if (! empty($parsed['strength'])) {
            $query->where('parsed_name', $parsed['name'])
                ->where('parsed_strength', $parsed['strength']);
        } else {
            $query->where('parsed_name', $parsed['name']);
        }

        $products = $query->get();

        // Fallback: coba pencarian exact canonical jika tersedia
        if ($products->isEmpty() && ! empty($parsed['canonical'])) {
            $fallback = NormalizedProduct::where('normalized_name', $parsed['canonical'])->first();
            if ($fallback) {
                $products = collect([$fallback]);
            }
        }

        if ($products->isEmpty()) {
            return null;
        }

        // Kumpulkan semua penawaran supplier dari semua varian produk yang cocok
        // (menghindari bug supplier terlewat karena perbedaan penulisan satuan Tablet/Strip/-)
        $supplierOffers = [];
        foreach ($products as $product) {
            $canonicalName = $product->normalized_name;
            foreach ($product->price_history_json ?? [] as $history) {
                $sId = (int) $history['supplier_id'];
                $price = (float) $history['price'];
                if (! isset($supplierOffers[$sId]) || $price < $supplierOffers[$sId]['price']) {
                    $supplierOffers[$sId] = array_merge($history, ['canonical' => $canonicalName]);
                }
            }
        }

        if (empty($supplierOffers)) {
            return null;
        }

        $bestPrice = PHP_FLOAT_MAX;
        $bestSupplier = null;
        $allCalculations = [];

        // Loop semua supplier yang punya harga untuk obat ini
        foreach ($supplierOffers as $offer) {
            $supplierId = $offer['supplier_id'];
            $basePrice = (float) $offer['price'];
            $rawName = $offer['raw_name'];

            $supplier = Supplier::find($supplierId);
            if (! $supplier || ! $supplier->is_active) {
                continue;
            }

            // 1. Cari Diskon (Cek Spesifik dulu, baru Global)
            $discountPct = $this->findDiscount($supplierId, $offer['canonical'], $qty);

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
                'unit' => $offer['unit'] ?? '-',
            ];

            $allCalculations[] = $result;

            if ($finalPrice < $bestPrice) {
                $bestPrice = $finalPrice;
                $bestSupplier = $result;
            }
        }

        if (! $bestSupplier) {
            return null;
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

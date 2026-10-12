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
        $matcher = app(DrugMatchingService::class);
        $match = $matcher->match($rawProductName);

        if ($match['status'] === 'not_stocked') {
            return [
                'winner' => [
                    'supplier_id' => 0,
                    'supplier_name' => 'BELANJA MANDIRI / NON-PBF',
                    'base_price' => 0,
                    'discount_pct' => 0,
                    'net_price' => 0,
                    'is_ppn_included' => true,
                    'final_price' => 0,
                    'raw_name' => $rawProductName,
                    'unit' => '-',
                ],
                'comparisons' => [],
                'match_info' => $match,
            ];
        }

        if (! $match['product']) {
            return [
                'winner' => null,
                'comparisons' => [],
                'match_info' => $match,
            ];
        }

        $matchedProduct = $match['product'];

        // Ambil produk dan variannya jika ada (misal beda penulisan satuan Tablet/Strip)
        $products = NormalizedProduct::query()
            ->where('parsed_name', $matchedProduct->parsed_name)
            ->when(! empty($matchedProduct->parsed_strength), function ($q) use ($matchedProduct) {
                $q->where('parsed_strength', $matchedProduct->parsed_strength);
            })
            ->get();

        if ($products->isEmpty()) {
            $products = collect([$matchedProduct]);
        }

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
            return [
                'winner' => null,
                'comparisons' => [],
                'match_info' => array_merge($match, [
                    'reason' => 'Produk cocok, tetapi belum ada data harga dari supplier',
                ]),
            ];
        }

        $bestPrice = PHP_FLOAT_MAX;
        $bestSupplier = null;
        $allCalculations = [];

        foreach ($supplierOffers as $offer) {
            $supplierId = $offer['supplier_id'];
            $basePrice = (float) $offer['price'];
            $rawName = $offer['raw_name'];

            $supplier = Supplier::find($supplierId);
            if (! $supplier || ! $supplier->is_active) {
                continue;
            }

            $discountPct = $this->findDiscount($supplierId, $offer['canonical'], $qty);
            $netPrice = $basePrice * (1 - ($discountPct / 100));

            $ppnRate = 0.12;
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
            return [
                'winner' => null,
                'comparisons' => [],
                'match_info' => array_merge($match, [
                    'reason' => 'Tidak ada penawaran dari supplier aktif',
                ]),
            ];
        }

        return [
            'winner' => $bestSupplier,
            'comparisons' => collect($allCalculations)->sortBy('final_price')->values()->toArray(),
            'match_info' => $match,
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

        return 0;
    }
}

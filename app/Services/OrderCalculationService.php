<?php

namespace App\Services;

use App\Models\NormalizedProduct;
use App\Models\ProductDiscountTier;
use App\Models\Supplier;
use App\Models\SupplierDiscountTier;
use Illuminate\Support\Collection;

class OrderCalculationService
{
    /** @var array<int, Supplier|null> */
    protected array $suppliersCache = [];

    /** @var array<int, Collection> */
    protected array $globalDiscountsCache = [];

    protected function getSupplier(int $supplierId): ?Supplier
    {
        if (! array_key_exists($supplierId, $this->suppliersCache)) {
            $this->suppliersCache[$supplierId] = Supplier::find($supplierId);
        }

        return $this->suppliersCache[$supplierId];
    }

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

        // Ambil produk dan semua variannya dari berbagai supplier (multi-master pooling)
        $products = $this->findEquivalentProducts($matchedProduct, $rawProductName);

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

            $supplier = $this->getSupplier($supplierId);
            if (! $supplier || ! $supplier->is_active) {
                continue;
            }

            // Prioritas diskon:
            // 1. ProductDiscountTier (manual/override dari admin atau upsert dari import)
            // 2. discount_pct yang tersimpan di price_history_json (dari file supplier)
            $discountPct = $this->findDiscount($supplierId, $offer['canonical'], $qty);
            if ($discountPct == 0 && isset($offer['discount_pct']) && $offer['discount_pct'] > 0) {
                $discountPct = (float) $offer['discount_pct'];
            }

            $netPrice = round($basePrice * (1 - ($discountPct / 100)), 2);

            $ppnRate = 0.12;
            $finalPrice = $supplier->is_ppn_included ? $netPrice : round($netPrice * (1 + $ppnRate), 2);

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
     * Cari persentase diskon tertinggi yang memenuhi min_qty.
     * Mendukung exact match dan fuzzy match (prefix kata pertama) untuk product_name.
     */
    private function findDiscount(int $supplierId, string $productName, int $qty): float
    {
        // 1. Exact match terlebih dulu
        $productDiscount = ProductDiscountTier::where('supplier_id', $supplierId)
            ->where('product_name', $productName)
            ->where('min_qty', '<=', $qty)
            ->orderByDesc('discount_pct')
            ->first();

        if ($productDiscount) {
            return (float) $productDiscount->discount_pct;
        }

        // 2. Fuzzy: cari tier yang product_name-nya merupakan substring dari canonical product name
        //    atau canonical product name merupakan substring dari product_name di tier.
        //    Gunakan prefix 4 karakter pertama sebagai pre-filter untuk performa.
        $prefix = mb_substr($productName, 0, 4);
        $tierCandidates = ProductDiscountTier::where('supplier_id', $supplierId)
            ->where('product_name', 'like', $prefix.'%')
            ->where('min_qty', '<=', $qty)
            ->orderByDesc('discount_pct')
            ->get();

        foreach ($tierCandidates as $tier) {
            $tierName = $tier->product_name;
            // Cocok jika salah satu mengandung yang lain (case-insensitive)
            if (stripos($productName, $tierName) !== false || stripos($tierName, $productName) !== false) {
                return (float) $tier->discount_pct;
            }
        }

        // 3. Cek diskon global supplier (cached per supplier)
        if (! array_key_exists($supplierId, $this->globalDiscountsCache)) {
            $this->globalDiscountsCache[$supplierId] = SupplierDiscountTier::where('supplier_id', $supplierId)
                ->orderByDesc('min_qty')
                ->get();
        }

        $globalDiscount = $this->globalDiscountsCache[$supplierId]
            ->first(fn ($tier) => $tier->min_qty <= $qty);

        if ($globalDiscount) {
            return (float) $globalDiscount->discount_pct;
        }

        return 0;
    }

    /**
     * Cari semua produk master yang setara / varian dari produk yang cocok
     * untuk mengumpulkan penawaran harga dari semua supplier (multi-master pooling).
     */
    protected function findEquivalentProducts(NormalizedProduct $matchedProduct, ?string $rawProductName = null): Collection
    {
        $products = collect([$matchedProduct]);
        $matcher = app(DrugMatchingService::class);

        // 1. Ambil produk dengan parsed_name yang sama persis
        $query = NormalizedProduct::query()
            ->where('parsed_name', $matchedProduct->parsed_name);

        if (! empty($matchedProduct->parsed_strength)) {
            $query->where('parsed_strength', $matchedProduct->parsed_strength);
        } else {
            $query->whereNull('parsed_strength');
        }

        $sameName = $query->get();
        $products = $products->merge($sameName);

        // 2. Cari varian dari supplier lain yang setara (misal 'AMPICILLIN NOVA/100' vs 'AMPICILLIN GEN. NOVA NOVAPHARIN')
        $firstWord = explode(' ', trim($matchedProduct->parsed_name))[0] ?? '';
        if (mb_strlen($firstWord) >= 3) {
            $candQuery = NormalizedProduct::query()
                ->where('id', '!=', $matchedProduct->id)
                ->where('parsed_name', 'like', mb_substr($firstWord, 0, 4).'%');

            if (! empty($matchedProduct->parsed_strength)) {
                $candQuery->where('parsed_strength', $matchedProduct->parsed_strength);
            } else {
                $candQuery->whereNull('parsed_strength');
            }

            $candidates = $candQuery->limit(200)->get();

            // Cek apakah order mentah meminta brand/pabrikan tertentu
            $orderBrand = null;
            $allowGenericCrossBrand = true;
            if ($rawProductName) {
                $brandKeywords = ['HJ', 'HEXPHARM', 'SHAMPARINDO', 'SAMPHAR', 'IFI', 'IFARS', 'NOVA', 'NOVAPHARIN', 'MERSI', 'DEXA', 'BERNO', 'TRIMAN', 'SELES', 'BETA', 'NOVEL', 'PIM', 'PHAPROS'];
                foreach ($brandKeywords as $bKw) {
                    if (preg_match('/\b'.preg_quote($bKw, '/').'\b/i', $rawProductName)) {
                        $orderBrand = $bKw;
                        $allowGenericCrossBrand = false;
                        break;
                    }
                }
            }

            foreach ($candidates as $candidate) {
                if ($matcher->areEquivalent($matchedProduct, $candidate, $orderBrand, $allowGenericCrossBrand)) {
                    $products->push($candidate);
                }
            }
        }

        return $products->unique('id')->values();
    }
}

<?php

namespace App\Console\Commands;

use App\Models\NormalizedProduct;
use App\Models\ProductDiscountTier;
use App\Models\RawProduct;
use App\Services\ProductNormalizationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

#[Signature('app:repair-discount-data')]
#[Description('Repair old raw_products data: restore HNA prices, fill discount_pct, and re-normalize')]
class RepairDiscountData extends Command
{
    public function handle(): int
    {
        $this->info('Starting discount data repair...');

        $hasDiscountPctColumn = Schema::hasColumn('raw_products', 'discount_pct');
        if (! $hasDiscountPctColumn) {
            $this->error('Column discount_pct does not exist on raw_products. Run php artisan migrate first.');

            return self::FAILURE;
        }

        // Step 1: Fix raw_products where discount > 0
        // Di file pricelist supplier (seperti LJS, SRI BUANA, dll.), kolom diskon berisi angka persentase (e.g. 3, 8, 15%).
        // Pada import versi lama, angka persentase ini keliru disimpan ke kolom 'discount' dan langsung dikurangi
        // dari harga (misal Rp 13.000 dikurangi 8 perak jadi Rp 12.992).
        // Kita pulihkan harga HNA asli dan isi kolom 'discount_pct' dengan persentase yang benar.
        $repairedCount = 0;
        RawProduct::where('discount', '>', 0)
            ->chunkById(200, function ($products) use (&$repairedCount) {
                foreach ($products as $raw) {
                    $currentPrice = (float) $raw->price;
                    $discountVal = (float) $raw->discount;

                    if ($discountVal <= 0) {
                        continue;
                    }

                    // Tentukan persentase diskon yang benar: angka <= 100 adalah persentase (e.g. 8 untuk 8%)
                    $discountPct = $discountVal <= 100
                        ? $discountVal
                        : round(($discountVal / ($currentPrice + $discountVal)) * 100, 4);

                    $currentDiscPct = (float) ($raw->discount_pct ?? 0);

                    // Kasus 1: Belum pernah diperbaiki (discount_pct masih 0/null)
                    // Pulihkan harga HNA asli (+ discountVal) dan simpan discount_pct yang benar
                    if ($currentDiscPct == 0) {
                        $originalHna = $currentPrice + $discountVal;
                        $raw->update([
                            'price' => $originalHna,
                            'discount_pct' => $discountPct,
                        ]);
                        $repairedCount++;
                    }
                    // Kasus 2: Pernah diperbaiki tapi dengan formula lama yang salah (menghasilkan angka kerdil seperti 0.06% atau 0.09%)
                    // Harganya sudah HNA asli, cukup perbaiki discount_pct-nya
                    elseif ($currentDiscPct < 1 && $discountVal >= 1) {
                        $raw->update([
                            'discount_pct' => $discountPct,
                        ]);
                        $repairedCount++;
                    }
                }
            });

        $this->info("Repaired {$repairedCount} raw_product records (restored HNA + filled discount_pct).");

        // Step 2: Clear and rebuild all normalized_products + ProductDiscountTier
        $this->info('Re-normalizing all products...');

        NormalizedProduct::query()->delete();
        ProductDiscountTier::query()->delete();

        $normalizer = app(ProductNormalizationService::class);
        $total = RawProduct::count();
        $bar = $this->output->createProgressBar($total);

        RawProduct::chunkById(200, function ($products) use ($normalizer, $bar) {
            foreach ($products as $raw) {
                $normalizer->normalizeAndCompare($raw);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $normalizedCount = NormalizedProduct::count();
        $tierCount = ProductDiscountTier::count();

        $this->info("Done! {$normalizedCount} normalized products, {$tierCount} discount tiers created.");

        // Step 3: Show sample to verify
        $samples = ProductDiscountTier::limit(10)->get(['product_name', 'supplier_id', 'discount_pct']);
        if ($samples->isNotEmpty()) {
            $this->info('Sample discount tiers:');
            $this->table(
                ['Product Name', 'Supplier ID', 'Discount %'],
                $samples->map(fn ($t) => [$t->product_name, $t->supplier_id, $t->discount_pct.'%'])->toArray()
            );
        }

        // Step 4: Spot-check a normalized product with discount
        $sampleNorm = NormalizedProduct::whereRaw("JSON_CONTAINS(price_history_json, '{\"discount_pct\": 0}') = 0")
            ->first();
        if (! $sampleNorm) {
            $sampleNorm = NormalizedProduct::whereRaw("price_history_json LIKE '%discount_pct%'")
                ->first();
        }
        if ($sampleNorm) {
            $this->info("Sample normalized product: {$sampleNorm->parsed_name}");
            $history = $sampleNorm->price_history_json;
            if (is_array($history)) {
                foreach ($history as $h) {
                    $disc = $h['discount_pct'] ?? 'N/A';
                    $this->line("  Supplier {$h['supplier_id']}: price={$h['price']}, discount_pct={$disc}%");
                }
            }
        }

        return self::SUCCESS;
    }
}

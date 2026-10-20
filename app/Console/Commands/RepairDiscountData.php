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

        // Step 1: Fix raw_products where discount > 0 but discount_pct is 0
        // These are old records where price was already reduced by discount nominal.
        // We reverse-calculate the original HNA and fill discount_pct.
        $repairedCount = 0;
        RawProduct::where('discount', '>', 0)
            ->where(function ($q) {
                $q->where('discount_pct', 0)->orWhereNull('discount_pct');
            })
            ->chunkById(200, function ($products) use (&$repairedCount) {
                foreach ($products as $raw) {
                    $currentPrice = (float) $raw->price;
                    $discountNominal = (float) $raw->discount;

                    // Reverse: original HNA = current_price + discount_nominal
                    $originalHna = $currentPrice + $discountNominal;

                    if ($originalHna <= 0) {
                        continue;
                    }

                    // Calculate percentage
                    $discountPct = round(($discountNominal / $originalHna) * 100, 4);

                    $raw->update([
                        'price' => $originalHna,
                        'discount_pct' => $discountPct,
                    ]);

                    $repairedCount++;
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

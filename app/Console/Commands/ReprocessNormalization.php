<?php

namespace App\Console\Commands;

use App\Models\NormalizedProduct;
use App\Models\RawProduct;
use App\Services\ProductNormalizationService;
use Illuminate\Console\Command;

class ReprocessNormalization extends Command
{
    protected $signature = 'app:reprocess-normalization
                            {--fresh : Truncate normalized_products before reprocessing}';

    protected $description = 'Rebuild normalized_products from existing raw_products data';

    public function handle(ProductNormalizationService $service): int
    {
        if ($this->option('fresh')) {
            NormalizedProduct::truncate();
            $this->info('Cleared normalized_products table.');
        }

        $total = RawProduct::count();

        if ($total === 0) {
            $this->warn('No raw products found. Upload a pricelist first.');

            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        RawProduct::with('uploadFile')->chunkById(200, function ($raws) use ($service, $bar) {
            foreach ($raws as $raw) {
                $service->normalizeAndCompare($raw);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done. Normalized products: '.NormalizedProduct::count());

        return self::SUCCESS;
    }
}

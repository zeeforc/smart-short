<?php

namespace App\Filament\Widgets;

use App\Models\NormalizedProduct;
use App\Models\Supplier;
use App\Models\UploadBatch;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseStatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Sales / PT', Supplier::count())
                ->description('Jumlah pemasok terdaftar')
                ->icon('heroicon-o-building-storefront')
                ->color('primary'),

            Stat::make('Sesi Import Selesai', UploadBatch::where('status', 'completed')->count())
                ->description('Jumlah batch pricelist')
                ->icon('heroicon-o-document-check')
                ->color('success'),

            Stat::make('Obat Tersortir', NormalizedProduct::count())
                ->description('Obat dengan harga terbaik')
                ->icon('heroicon-o-trophy')
                ->color('info'),
        ];
    }
}

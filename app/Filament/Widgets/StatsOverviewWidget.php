<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseStatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Sales / PT', \App\Models\Supplier::count())
                ->description('Jumlah pemasok terdaftar')
                ->icon('heroicon-o-building-storefront')
                ->color('primary'),
                
            Stat::make('Sesi Import Selesai', \App\Models\UploadBatch::where('status', 'completed')->count())
                ->description('Jumlah batch pricelist')
                ->icon('heroicon-o-document-check')
                ->color('success'),
                
            Stat::make('Obat Tersortir', \App\Models\NormalizedProduct::count())
                ->description('Obat dengan harga terbaik')
                ->icon('heroicon-o-trophy')
                ->color('info'),
        ];
    }
}

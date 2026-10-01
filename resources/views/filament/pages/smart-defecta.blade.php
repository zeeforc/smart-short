<x-filament-panels::page>
    {{ $this->table }}

    @if($calculationResults !== null)
        @php
            $totalItems   = count($calculationResults);
            $foundItems   = collect($calculationResults)->filter(fn($r) => $r['calculation'])->count();
            $missingItems = $totalItems - $foundItems;
            $grandTotal   = collect($calculationResults)
                ->filter(fn($r) => $r['calculation'])
                ->sum(fn($r) => $r['calculation']['winner']['final_price'] * $r['request']['qty']);
            $suppliers = collect($calculationResults)
                ->filter(fn($r) => $r['calculation'])
                ->pluck('calculation.winner.supplier_name')
                ->unique()
                ->sort()
                ->values();
        @endphp

        <div class="mt-8 space-y-6">

            {{-- Header bar --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Hasil Rekomendasi</h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Harga sudah memperhitungkan diskon. PPN 12% ditambahkan jika belum termasuk dalam pricelist.</p>
                </div>
                <x-filament::button
                    wire:click="exportToExcel"
                    color="success"
                    icon="heroicon-m-arrow-down-tray"
                    size="md"
                >
                    Download Surat Pesanan (Excel)
                </x-filament::button>
            </div>

            {{-- Summary strip --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-white/5">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Obat</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $totalItems }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-white/5">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Ditemukan</p>
                    <p class="mt-1 text-xl font-semibold text-success-600 dark:text-success-400">{{ $foundItems }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-white/5">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Belum Ada Harga</p>
                    <p class="mt-1 text-xl font-semibold {{ $missingItems > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-400 dark:text-gray-500' }}">{{ $missingItems }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-white/5">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Estimasi Total</p>
                    <p class="mt-1 text-base font-semibold text-gray-900 dark:text-white">Rp {{ number_format($grandTotal, 0, ',', '.') }}</p>
                </div>
            </div>

            {{-- Supplier chips --}}
            @if($suppliers->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach($suppliers as $sup)
                        <span class="inline-flex items-center rounded-md bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-200 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-500/30">
                            {{ $sup }}
                        </span>
                    @endforeach
                </div>
            @endif

            {{-- Result cards --}}
            <div class="space-y-3">
                @foreach($calculationResults as $index => $res)
                    @php $calc = $res['calculation']; @endphp

                    <div class="overflow-hidden rounded-xl border bg-white dark:bg-gray-900 {{ $calc ? 'border-gray-200 dark:border-white/10' : 'border-warning-200 dark:border-warning-500/30' }}">

                        {{-- Card header --}}
                        <div class="flex items-start justify-between gap-4 px-5 py-4 {{ $calc ? 'border-b border-gray-100 dark:border-white/5' : '' }}">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $res['request']['product_name'] }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                    Jumlah dipesan: <span class="font-medium text-gray-600 dark:text-gray-300">{{ $res['request']['qty'] }}</span> item
                                </p>
                            </div>

                            @if($calc)
                                <div class="flex-shrink-0 text-right">
                                    <p class="text-xs text-gray-400 dark:text-gray-500">Total Harga</p>
                                    <p class="text-base font-bold text-gray-900 dark:text-white">
                                        Rp {{ number_format($calc['winner']['final_price'] * $res['request']['qty'], 0, ',', '.') }}
                                    </p>
                                </div>
                            @else
                                <span class="inline-flex items-center rounded-md bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-700 ring-1 ring-inset ring-warning-200 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/30">
                                    Belum Ada Pricelist
                                </span>
                            @endif
                        </div>

                        @if($calc)
                            <div class="px-5 py-4">
                                <div class="flex flex-col gap-4 md:flex-row md:gap-8">

                                    {{-- Winner detail --}}
                                    <div class="flex-shrink-0">
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-success-700 dark:text-success-400">Supplier Terpilih</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $calc['winner']['supplier_name'] }}</p>
                                        <dl class="mt-2 space-y-1">
                                            <div class="flex gap-2 text-xs">
                                                <dt class="w-28 text-gray-400 dark:text-gray-500">Harga dasar</dt>
                                                <dd class="font-mono text-gray-700 dark:text-gray-200">Rp {{ number_format($calc['winner']['base_price'], 0, ',', '.') }}</dd>
                                            </div>
                                            @if($calc['winner']['discount_pct'] > 0)
                                                <div class="flex gap-2 text-xs">
                                                    <dt class="w-28 text-gray-400 dark:text-gray-500">Potongan diskon</dt>
                                                    <dd class="font-mono font-medium text-success-600 dark:text-success-400">{{ $calc['winner']['discount_pct'] }}%</dd>
                                                </div>
                                            @endif
                                            <div class="flex gap-2 text-xs">
                                                <dt class="w-28 text-gray-400 dark:text-gray-500">Status PPN</dt>
                                                <dd class="text-gray-700 dark:text-gray-200">{{ $calc['winner']['is_ppn_included'] ? 'Sudah termasuk dalam harga' : 'Ditambah 12%' }}</dd>
                                            </div>
                                            <div class="flex gap-2 border-t border-gray-100 pt-1 text-xs dark:border-white/5"> {{-- baris harga final --}}
                                                <dt class="w-28 font-medium text-gray-600 dark:text-gray-300">Harga final/item</dt>
                                                <dd class="font-mono font-semibold text-gray-900 dark:text-white">Rp {{ number_format($calc['winner']['final_price'], 0, ',', '.') }}</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    {{-- Comparison table --}}
                                    @php
                                        $others = collect($calc['comparisons'])
                                            ->filter(fn($c) => $c['supplier_id'] !== $calc['winner']['supplier_id'])
                                            ->values();
                                    @endphp
                                    @if($others->isNotEmpty())
                                        <div class="flex-1 overflow-x-auto">
                                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Perbandingan Supplier Lain</p>
                                            <table class="w-full min-w-max text-xs">
                                                <thead>
                                                    <tr class="text-left">
                                                        <th class="pb-1.5 pr-4 font-medium text-gray-400 dark:text-gray-500">Supplier</th>
                                                        <th class="pb-1.5 pr-4 text-right font-medium text-gray-400 dark:text-gray-500">Diskon</th>
                                                        <th class="pb-1.5 text-right font-medium text-gray-400 dark:text-gray-500">Harga Final/item</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                                    @foreach($others as $comp)
                                                        <tr>
                                                            <td class="py-1.5 pr-4 text-gray-700 dark:text-gray-200">{{ $comp['supplier_name'] }}</td>
                                                            <td class="py-1.5 pr-4 text-right font-mono text-gray-500 dark:text-gray-400">{{ $comp['discount_pct'] > 0 ? $comp['discount_pct'].'%' : '-' }}</td>
                                                            <td class="py-1.5 text-right font-mono text-gray-700 dark:text-gray-200">Rp {{ number_format($comp['final_price'], 0, ',', '.') }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>

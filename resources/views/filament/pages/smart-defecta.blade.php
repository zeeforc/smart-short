<x-filament-panels::page>
    <!-- Render the table -->
    {{ $this->table }}

    <!-- Render calculation results below the table -->
    @if($calculationResults !== null)
        <div class="mt-8 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold">Hasil Rekomendasi (Termasuk Diskon & PPN)</h2>
                <x-filament::button wire:click="exportToExcel" color="success" icon="heroicon-m-arrow-down-tray">
                    Download Surat Pesanan (Excel)
                </x-filament::button>
            </div>
            
            @foreach($calculationResults as $res)
                <x-filament::section>
                    <x-slot name="heading">
                        {{ $res['request']['product_name'] }} (Dibutuhkan: {{ $res['request']['qty'] }})
                    </x-slot>

                    @if($res['calculation'])
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <h3 class="text-lg font-bold text-success-600 dark:text-success-400">Pemenang: {{ $res['calculation']['winner']['supplier_name'] }}</h3>
                                <ul class="mt-2 space-y-1 text-sm">
                                    <li>Harga Dasar: Rp {{ number_format($res['calculation']['winner']['base_price'], 0, ',', '.') }}</li>
                                    <li>Diskon Diterapkan: {{ $res['calculation']['winner']['discount_pct'] }}%</li>
                                    <li>PPN: {{ $res['calculation']['winner']['is_ppn_included'] ? 'Sudah Termasuk' : '+12%' }}</li>
                                    <li class="font-bold">Harga Final/item: Rp {{ number_format($res['calculation']['winner']['final_price'], 2, ',', '.') }}</li>
                                    <li class="font-bold text-lg mt-2">Total Harga: Rp {{ number_format($res['calculation']['winner']['final_price'] * $res['request']['qty'], 2, ',', '.') }}</li>
                                </ul>
                            </div>
                            
                            <div>
                                <h4 class="font-semibold text-sm text-gray-500">Perbandingan Supplier Lain:</h4>
                                <ul class="mt-2 space-y-2 text-xs">
                                    @foreach($res['calculation']['comparisons'] as $comp)
                                        @if($comp['supplier_id'] !== $res['calculation']['winner']['supplier_id'])
                                            <li class="flex justify-between border-b pb-1">
                                                <span>{{ $comp['supplier_name'] }} <br> <span class="text-gray-400">(Diskon {{ $comp['discount_pct'] }}%)</span></span>
                                                <span class="font-mono">Rp {{ number_format($comp['final_price'], 2, ',', '.') }}</span>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @else
                        <p class="text-danger-600 text-sm">Obat tidak ditemukan di database atau belum ada harga.</p>
                    @endif
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>

<x-filament-panels::page>
    <form wire:submit="calculate">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" color="primary" icon="heroicon-m-calculator">
                Kalkulasi Pemenang
            </x-filament::button>
        </div>
    </form>

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

                    @if(!$res['calculation'])
                        <div class="text-danger-600">Obat tidak ditemukan di database atau belum ada harga.</div>
                    @else
                        <div class="mb-4 p-4 rounded-lg bg-primary-50 dark:bg-primary-900 border border-primary-200 dark:border-primary-700">
                            <h3 class="font-bold text-primary-600 dark:text-primary-400">🏆 Pemenang Termurah: {{ $res['calculation']['winner']['supplier_name'] }}</h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-2">
                                <div>
                                    <span class="text-sm text-gray-500 block">Harga Dasar</span>
                                    Rp {{ number_format($res['calculation']['winner']['base_price'], 0, ',', '.') }}
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500 block">Diskon Didapat</span>
                                    {{ $res['calculation']['winner']['discount_pct'] }}%
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500 block">PPN (12%)</span>
                                    {!! $res['calculation']['winner']['is_ppn_included'] ? '<span class="text-success-600">Sudah Termasuk</span>' : '<span class="text-warning-600">+12% (Belum Termasuk)</span>' !!}
                                </div>
                                <div>
                                    <span class="text-sm font-bold block">Harga Final / item</span>
                                    <span class="text-lg font-bold text-primary-600">Rp {{ number_format($res['calculation']['winner']['final_price'], 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>

                        <details>
                            <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 mb-2">Lihat Perbandingan Lengkap</summary>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-2">Supplier</th>
                                            <th class="px-4 py-2">Harga Dasar</th>
                                            <th class="px-4 py-2">Diskon</th>
                                            <th class="px-4 py-2">PPN 12%</th>
                                            <th class="px-4 py-2">Harga Final</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($res['calculation']['comparisons'] as $comp)
                                            <tr>
                                                <td class="px-4 py-2">{{ $comp['supplier_name'] }}</td>
                                                <td class="px-4 py-2">Rp {{ number_format($comp['base_price'], 0, ',', '.') }}</td>
                                                <td class="px-4 py-2 text-success-600">{{ $comp['discount_pct'] > 0 ? $comp['discount_pct'].'%' : '-' }}</td>
                                                <td class="px-4 py-2">
                                                    @if($comp['is_ppn_included'])
                                                        <span class="text-xs text-success-600">Termasuk</span>
                                                    @else
                                                        <span class="text-xs text-warning-600">Ditambahkan</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2 font-bold {{ $loop->first ? 'text-primary-600' : '' }}">
                                                    Rp {{ number_format($comp['final_price'], 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    @endif
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>

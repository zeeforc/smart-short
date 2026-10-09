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
        @endphp

        <style>
            .sd-section { margin-top: 2rem; }

            /* Summary strip */
            .sd-summary {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 0.75rem;
                margin-bottom: 1.5rem;
            }
            @media (max-width: 640px) { .sd-summary { grid-template-columns: repeat(2, 1fr); } }
            .sd-stat {
                background: var(--fi-bg, #fff);
                border: 1px solid color-mix(in srgb, currentColor 12%, transparent);
                border-radius: 0.75rem;
                padding: 1rem 1.25rem;
            }
            .dark .sd-stat { background: rgba(255,255,255,0.04); }
            .sd-stat-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 0.25rem; }
            .sd-stat-value { font-size: 1.25rem; font-weight: 700; color: #111827; }
            .dark .sd-stat-value { color: #f9fafb; }
            .sd-stat-value.success { color: #16a34a; }
            .sd-stat-value.warning { color: #d97706; }
            .sd-stat-value.money { font-size: 1rem; }

            /* Result card */
            .sd-card {
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 0.875rem;
                overflow: hidden;
                margin-bottom: 0.75rem;
            }
            .dark .sd-card { background: #1f2937; border-color: rgba(255,255,255,0.08); }
            .sd-card.missing { border-color: #fbbf24; }
            .dark .sd-card.missing { border-color: rgba(251,191,36,0.35); }

            .sd-card-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: 0.875rem 1.25rem;
                border-bottom: 1px solid #f3f4f6;
            }
            .dark .sd-card-header { border-bottom-color: rgba(255,255,255,0.05); }
            .sd-card.missing .sd-card-header { border-bottom: none; }

            .sd-drug-name { font-size: 0.875rem; font-weight: 600; color: #111827; }
            .dark .sd-drug-name { color: #f9fafb; }
            .sd-drug-qty { font-size: 0.7rem; color: #9ca3af; margin-top: 0.125rem; }
            .sd-drug-qty b { color: #4b5563; }
            .dark .sd-drug-qty b { color: #d1d5db; }

            .sd-total-label { font-size: 0.65rem; color: #9ca3af; text-align: right; }
            .sd-total-value { font-size: 1rem; font-weight: 700; color: #111827; text-align: right; }
            .dark .sd-total-value { color: #f9fafb; }

            .sd-badge-missing {
                font-size: 0.7rem; font-weight: 600;
                background: #fef3c7; color: #92400e;
                border: 1px solid #fde68a;
                border-radius: 0.375rem;
                padding: 0.25rem 0.625rem;
                white-space: nowrap;
            }
            .dark .sd-badge-missing { background: rgba(251,191,36,0.1); color: #fbbf24; border-color: rgba(251,191,36,0.3); }

            /* Card body */
            .sd-card-body {
                padding: 1rem 1.25rem;
                display: flex;
                gap: 2rem;
                flex-wrap: wrap;
            }

            .sd-winner { min-width: 200px; flex-shrink: 0; }
            .sd-section-label {
                font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
                letter-spacing: 0.07em; margin-bottom: 0.5rem;
            }
            .sd-section-label.green { color: #16a34a; }
            .sd-section-label.gray { color: #9ca3af; }

            .sd-winner-name { font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem; }
            .dark .sd-winner-name { color: #f9fafb; }

            .sd-dl { display: grid; grid-template-columns: auto 1fr; gap: 0.25rem 0.75rem; font-size: 0.75rem; align-items: baseline; }
            .sd-dt { color: #9ca3af; white-space: nowrap; }
            .sd-dd { color: #374151; font-variant-numeric: tabular-nums; }
            .dark .sd-dd { color: #d1d5db; }
            .sd-dd.discount { color: #16a34a; font-weight: 600; }
            .sd-dd.bold { font-weight: 700; color: #111827; }
            .dark .sd-dd.bold { color: #f9fafb; }
            .sd-divider { grid-column: 1 / -1; border: none; border-top: 1px solid #f3f4f6; margin: 0.25rem 0; }
            .dark .sd-divider { border-top-color: rgba(255,255,255,0.06); }

            /* Comparison table */
            .sd-compare { flex: 1; min-width: 220px; overflow-x: auto; }
            .sd-compare table { width: 100%; border-collapse: collapse; font-size: 0.75rem; }
            .sd-compare th { color: #9ca3af; font-weight: 600; text-align: left; padding-bottom: 0.375rem; padding-right: 1rem; white-space: nowrap; }
            .sd-compare th:last-child { text-align: right; padding-right: 0; }
            .sd-compare td { color: #374151; padding: 0.3rem 1rem 0.3rem 0; border-top: 1px solid #f3f4f6; white-space: nowrap; }
            .dark .sd-compare td { color: #d1d5db; border-top-color: rgba(255,255,255,0.05); }
            .dark .sd-compare th { color: #6b7280; }
            .sd-compare td:last-child { text-align: right; padding-right: 0; font-variant-numeric: tabular-nums; }
            .sd-compare td.mono { font-variant-numeric: tabular-nums; }

            /* Pagination */
            .sd-pagination {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-top: 1rem;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            .sd-page-info { font-size: 0.8rem; color: #6b7280; }
            .sd-page-btns { display: flex; gap: 0.375rem; }
            .sd-page-btn {
                min-width: 2rem; height: 2rem;
                display: inline-flex; align-items: center; justify-content: center;
                border-radius: 0.5rem;
                font-size: 0.8rem; font-weight: 500;
                cursor: pointer;
                border: 1px solid #e5e7eb;
                background: #fff; color: #374151;
                transition: background 0.15s;
            }
            .dark .sd-page-btn { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: #d1d5db; }
            .sd-page-btn:hover { background: #f3f4f6; }
            .dark .sd-page-btn:hover { background: rgba(255,255,255,0.1); }
            .sd-page-btn.active { background: #4f46e5; color: #fff; border-color: #4f46e5; }
            .sd-page-btn:disabled { opacity: 0.35; cursor: not-allowed; }

            .sd-header-bar {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 1rem;
                flex-wrap: wrap;
                margin-bottom: 1.25rem;
            }
            .sd-title { font-size: 1rem; font-weight: 700; color: #111827; }
            .dark .sd-title { color: #f9fafb; }
            .sd-subtitle { font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem; }
        </style>

        <div class="sd-section" x-data="{
            perPage: 10,
            currentPage: 1,
            total: {{ $totalItems }},
            get totalPages() { return Math.ceil(this.total / this.perPage); },
            get start() { return (this.currentPage - 1) * this.perPage; },
            get end() { return Math.min(this.currentPage * this.perPage, this.total); },
            isVisible(index) { return index >= this.start && index < this.end; },
            pages() {
                let p = [];
                for (let i = 1; i <= this.totalPages; i++) p.push(i);
                return p;
            }
        }">
            {{-- Header --}}
            <div class="sd-header-bar">
                <div>
                    <p class="sd-title">Hasil Rekomendasi</p>
                    <p class="sd-subtitle">Harga sudah memperhitungkan diskon. PPN 12% ditambahkan jika belum termasuk dalam pricelist.</p>
                </div>
                <x-filament::button wire:click="exportToExcel" color="success" icon="heroicon-m-arrow-down-tray" size="sm">
                    Download Surat Pesanan (Excel)
                </x-filament::button>
            </div>

            {{-- Summary strip --}}
            <div class="sd-summary">
                <div class="sd-stat">
                    <div class="sd-stat-label">Total Obat</div>
                    <div class="sd-stat-value">{{ $totalItems }}</div>
                </div>
                <div class="sd-stat">
                    <div class="sd-stat-label">Ditemukan</div>
                    <div class="sd-stat-value success">{{ $foundItems }}</div>
                </div>
                <div class="sd-stat">
                    <div class="sd-stat-label">Belum Ada Harga</div>
                    <div class="sd-stat-value {{ $missingItems > 0 ? 'warning' : '' }}">{{ $missingItems }}</div>
                </div>
                <div class="sd-stat">
                    <div class="sd-stat-label">Estimasi Total</div>
                    <div class="sd-stat-value money">Rp {{ number_format($grandTotal, 0, ',', '.') }}</div>
                </div>
            </div>

            {{-- Cards --}}
            @foreach($calculationResults as $index => $res)
                @php $calc = $res['calculation']; @endphp
                <div class="sd-card {{ $calc ? '' : 'missing' }}" x-show="isVisible({{ $index }})">
                    <div class="sd-card-header">
                        <div>
                            <div class="sd-drug-name">{{ $res['request']['product_name'] }}</div>
                            <div class="sd-drug-qty">Jumlah dipesan: <b>{{ $res['request']['qty'] }} item</b></div>
                        </div>
                        @if($calc)
                            <div>
                                <div class="sd-total-label">Total harga</div>
                                <div class="sd-total-value">Rp {{ number_format($calc['winner']['final_price'] * $res['request']['qty'], 0, ',', '.') }}</div>
                            </div>
                        @else
                            <button 
                                wire:click="mountAction('jodohkan', { raw_name: '{{ addslashes($res['request']['product_name']) }}' })"
                                class="sd-badge-missing"
                                style="cursor: pointer; display: flex; align-items: center; gap: 0.25rem;"
                                title="Klik untuk mencocokkan obat secara manual"
                            >
                                <svg style="width: 1rem; height: 1rem;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                </svg>
                                Belum Ada Pricelist (Jodohkan)
                            </button>
                        @endif
                    </div>

                    @if($calc)
                        <div class="sd-card-body">
                            {{-- Winner --}}
                            <div class="sd-winner">
                                <div class="sd-section-label green">Supplier Terpilih</div>
                                <div class="sd-winner-name">{{ $calc['winner']['supplier_name'] }}</div>
                                <dl class="sd-dl">
                                    <dt class="sd-dt">Harga dasar</dt>
                                    <dd class="sd-dd">Rp {{ number_format($calc['winner']['base_price'], 0, ',', '.') }}</dd>
                                    @if($calc['winner']['discount_pct'] > 0)
                                        <dt class="sd-dt">Potongan diskon</dt>
                                        <dd class="sd-dd discount">{{ $calc['winner']['discount_pct'] }}%</dd>
                                    @endif
                                    <dt class="sd-dt">Status PPN</dt>
                                    <dd class="sd-dd">{{ $calc['winner']['is_ppn_included'] ? 'Sudah termasuk' : 'Ditambah 12%' }}</dd>
                                    <hr class="sd-divider">
                                    <dt class="sd-dt">Harga final/item</dt>
                                    <dd class="sd-dd bold">Rp {{ number_format($calc['winner']['final_price'], 0, ',', '.') }}</dd>
                                </dl>
                            </div>

                            {{-- Comparison --}}
                            @php
                                $others = collect($calc['comparisons'])
                                    ->filter(fn($c) => $c['supplier_id'] !== $calc['winner']['supplier_id'])
                                    ->values();
                            @endphp
                            @if($others->isNotEmpty())
                                <div class="sd-compare">
                                    <div class="sd-section-label gray">Perbandingan Supplier Lain</div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Supplier</th>
                                                <th>Diskon</th>
                                                <th style="text-align:right">Harga Final/item</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($others as $comp)
                                                <tr>
                                                    <td>{{ $comp['supplier_name'] }}</td>
                                                    <td class="mono">{{ $comp['discount_pct'] > 0 ? $comp['discount_pct'].'%' : '-' }}</td>
                                                    <td>Rp {{ number_format($comp['final_price'], 0, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- Pagination --}}
            <div class="sd-pagination">
                <span class="sd-page-info">
                    Menampilkan <span x-text="start + 1"></span>–<span x-text="end"></span> dari {{ $totalItems }} obat
                </span>
                <div class="sd-page-btns">
                    <button class="sd-page-btn" @click="currentPage--" :disabled="currentPage === 1">&lsaquo;</button>
                    <template x-for="p in pages()" :key="p">
                        <button class="sd-page-btn" :class="{ active: currentPage === p }" @click="currentPage = p" x-text="p"></button>
                    </template>
                    <button class="sd-page-btn" @click="currentPage++" :disabled="currentPage === totalPages">&rsaquo;</button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>

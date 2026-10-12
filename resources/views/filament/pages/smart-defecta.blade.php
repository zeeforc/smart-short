<x-filament-panels::page>
    {{ $this->table }}

    @if($calculationResults !== null)
        @php
            $totalItems   = count($calculationResults);
            $foundItems   = collect($calculationResults)->filter(fn($r) => !empty($r['calculation']['winner']))->count();
            
            // Item yang butuh perhatian manual: item tanpa pemenang, KECUALI yang sudah ditandai 'unlisted'
            $unmatchedList = collect($calculationResults)->filter(function($r) {
                $status = $r['calculation']['match_info']['status'] ?? '';
                return empty($r['calculation']['winner']) && $status !== 'unlisted';
            })->values();
            
            $missingItems = $unmatchedList->count();
            $grandTotal   = collect($calculationResults)
                ->filter(fn($r) => !empty($r['calculation']['winner']))
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

            /* Alert Box for Unmatched Items */
            .sd-alert-box {
                background: #fffbeb;
                border: 1px solid #fde68a;
                border-radius: 0.875rem;
                padding: 1.25rem 1.5rem;
                margin-bottom: 1.75rem;
            }
            .dark .sd-alert-box {
                background: rgba(245, 158, 11, 0.08);
                border-color: rgba(245, 158, 11, 0.25);
            }
            .sd-alert-top {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 1rem;
                margin-bottom: 0.875rem;
            }
            .sd-alert-title-wrap {
                display: flex;
                align-items: flex-start;
                gap: 0.75rem;
            }
            .sd-alert-icon {
                width: 1.35rem;
                height: 1.35rem;
                color: #d97706;
                flex-shrink: 0;
                margin-top: 0.125rem;
            }
            .dark .sd-alert-icon { color: #fbbf24; }
            .sd-alert-heading {
                font-size: 0.95rem;
                font-weight: 700;
                color: #92400e;
                margin: 0;
            }
            .dark .sd-alert-heading { color: #fef3c7; }
            .sd-alert-sub {
                font-size: 0.775rem;
                color: #b45309;
                margin: 0.25rem 0 0 0;
            }
            .dark .sd-alert-sub { color: #fde68a; }
            .sd-alert-items {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            .sd-alert-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                background: #ffffff;
                border: 1px solid #fef3c7;
                border-radius: 0.625rem;
                padding: 0.625rem 1rem;
            }
            .dark .sd-alert-row {
                background: rgba(31, 41, 55, 0.6);
                border-color: rgba(245, 158, 11, 0.15);
            }
            .sd-alert-row-left {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                flex-wrap: wrap;
            }
            .sd-alert-badge-num {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 1.35rem;
                height: 1.35rem;
                border-radius: 9999px;
                background: #fde68a;
                color: #78350f;
                font-size: 0.7rem;
                font-weight: 700;
                flex-shrink: 0;
            }
            .dark .sd-alert-badge-num {
                background: rgba(245, 158, 11, 0.25);
                color: #fef3c7;
            }
            .sd-alert-drug {
                font-size: 0.85rem;
                font-weight: 700;
                color: #1f2937;
            }
            .dark .sd-alert-drug { color: #f9fafb; }
            .sd-alert-qty {
                font-size: 0.75rem;
                color: #6b7280;
                margin-left: 0.25rem;
            }
            .sd-alert-sugg-text {
                font-size: 0.725rem;
                color: #047857;
                margin-top: 0.15rem;
            }
            .dark .sd-alert-sugg-text { color: #34d399; }
            .sd-alert-none-text {
                font-size: 0.725rem;
                color: #b45309;
                margin-top: 0.15rem;
            }
            .dark .sd-alert-none-text { color: #fbbf24; }
            .sd-alert-action-btn {
                background: #f59e0b;
                color: #ffffff;
                font-size: 0.75rem;
                font-weight: 600;
                border: none;
                border-radius: 0.5rem;
                padding: 0.4rem 0.85rem;
                cursor: pointer;
                white-space: nowrap;
                transition: background 0.15s ease;
            }
            .sd-alert-action-btn:hover { background: #d97706; }
            .sd-alert-btn-nonpbf {
                background: #f3f4f6;
                color: #374151;
                font-size: 0.725rem;
                font-weight: 600;
                border: 1px solid #d1d5db;
                border-radius: 0.5rem;
                padding: 0.4rem 0.75rem;
                cursor: pointer;
                white-space: nowrap;
                transition: background 0.15s ease;
            }
            .sd-alert-btn-nonpbf:hover { background: #e5e7eb; }
            .dark .sd-alert-btn-nonpbf {
                background: rgba(255, 255, 255, 0.08);
                color: #e5e7eb;
                border-color: rgba(255, 255, 255, 0.15);
            }
            .dark .sd-alert-btn-nonpbf:hover { background: rgba(255, 255, 255, 0.15); }

            .sd-match-badge {
                font-size: 0.65rem;
                font-weight: 600;
                border-radius: 0.25rem;
                padding: 0.15rem 0.45rem;
                margin-left: 0.5rem;
                display: inline-block;
                vertical-align: middle;
            }
            .sd-match-badge.auto {
                background: #dcfce7;
                color: #15803d;
                border: 1px solid #bbf7d0;
            }
            .dark .sd-match-badge.auto {
                background: rgba(22, 163, 74, 0.15);
                color: #4ade80;
                border-color: rgba(22, 163, 74, 0.3);
            }
            .sd-match-badge.alias {
                background: #e0e7ff;
                color: #4338ca;
                border: 1px solid #c7d2fe;
            }
            .dark .sd-match-badge.alias {
                background: rgba(79, 70, 229, 0.15);
                color: #818cf8;
                border-color: rgba(79, 70, 229, 0.3);
            }
            .sd-match-badge.nonpbf {
                background: #f3f4f6;
                color: #4b5563;
                border: 1px solid #e5e7eb;
            }
            .dark .sd-match-badge.nonpbf {
                background: rgba(255, 255, 255, 0.08);
                color: #d1d5db;
                border-color: rgba(255, 255, 255, 0.15);
            }
            .sd-match-badge.unlisted {
                background: #fef3c7;
                color: #92400e;
                border: 1px solid #fde68a;
            }
            .dark .sd-match-badge.unlisted {
                background: rgba(251, 191, 36, 0.1);
                color: #fbbf24;
                border-color: rgba(251, 191, 36, 0.3);
            }
            .sd-sugg-inline {
                font-size: 0.7rem;
                color: #059669;
            }
            .dark .sd-sugg-inline { color: #34d399; }

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

            {{-- Alert Banner: Rincian Obat yang Perlu Dicocokkan Manual --}}
            @if($missingItems > 0)
                <div class="sd-alert-box">
                    <div class="sd-alert-top">
                        <div class="sd-alert-title-wrap">
                            <svg class="sd-alert-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <h4 class="sd-alert-heading">Perlu Perhatian: {{ $missingItems }} Obat Belum Terhubung ke Pricelist</h4>
                                <p class="sd-alert-sub">Obat berikut tidak lolos auto-match $\ge$ 85% dan butuh konfirmasi manual atau ditandai bukan stok PBF:</p>
                            </div>
                        </div>
                    </div>
                    <div class="sd-alert-items">
                        @foreach($unmatchedList as $unmatched)
                            @php
                                $uName = $unmatched['request']['product_name'];
                                $uQty = $unmatched['request']['qty'];
                                $uInfo = $unmatched['calculation']['match_info'] ?? null;
                                $topSugg = $uInfo['suggestions'][0] ?? null;
                            @endphp
                            <div class="sd-alert-row">
                                <div class="sd-alert-row-left">
                                    <span class="sd-alert-badge-num">{{ $loop->iteration }}</span>
                                    <div>
                                        <span class="sd-alert-drug">{{ $uName }}</span>
                                        <span class="sd-alert-qty">({{ $uQty }} item)</span>
                                        @if($topSugg && ($topSugg['confidence'] ?? 0) >= 70)
                                            <div class="sd-alert-sugg-text">
                                                💡 Rekomendasi terdekat: <strong>{{ $topSugg['name'] }}</strong> (Akurasi: {{ round($topSugg['confidence']) }}%)
                                            </div>
                                        @else
                                            <div class="sd-alert-none-text">
                                                {{ $uInfo['reason'] ?? 'Tidak ditemukan di pricelist supplier manapun' }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0;">
                                    <button 
                                        type="button"
                                        wire:click="mountAction('jodohkan', { raw_name: '{{ addslashes($uName) }}', suggested_id: '{{ ($topSugg && ($topSugg['confidence'] ?? 0) >= 70) ? ($topSugg['product']['id'] ?? '') : '' }}' })"
                                        class="sd-alert-action-btn"
                                    >
                                        Jodohkan Manual
                                    </button>
                                    <button 
                                        type="button"
                                        wire:click="mountAction('tandaiNonPbf', { raw_name: '{{ addslashes($uName) }}' })"
                                        class="sd-alert-btn-nonpbf"
                                        title="Tandai produk ini bukan dari distributor obat (swalayan / belanja mandiri)"
                                    >
                                        🛒 Bukan Stok PBF
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Cards --}}
            @foreach($calculationResults as $index => $res)
                @php 
                    $calc = $res['calculation']; 
                    $hasWinner = !empty($calc['winner']);
                    $isNonPbf = $hasWinner && (($calc['winner']['supplier_name'] ?? '') === 'BELANJA MANDIRI / NON-PBF');
                @endphp
                <div class="sd-card {{ $hasWinner ? '' : 'missing' }}" x-show="isVisible({{ $index }})">
                    <div class="sd-card-header">
                        <div>
                            <div class="sd-drug-name">
                                {{ $res['request']['product_name'] }}
                                @if(!empty($calc['match_info']))
                                    @php $mStatus = $calc['match_info']['status'] ?? ''; @endphp
                                    @if($mStatus === 'not_stocked' || $isNonPbf)
                                        <span class="sd-match-badge nonpbf">🛒 Belanja Mandiri (Non-PBF)</span>
                                    @elseif($mStatus === 'unlisted')
                                        <span class="sd-match-badge unlisted">📦 Belum Ada di PBF Rekanan</span>
                                    @elseif($mStatus === 'auto_matched')
                                        <span class="sd-match-badge auto" title="Dicocokkan otomatis oleh sistem">⚡ Auto-Matched ({{ round($calc['match_info']['confidence']) }}%)</span>
                                    @elseif($mStatus === 'alias')
                                        <span class="sd-match-badge alias" title="Tersimpan di Kamus Alias">Kamus Alias</span>
                                    @endif
                                @endif
                            </div>
                            <div class="sd-drug-qty">Jumlah dipesan: <b>{{ $res['request']['qty'] }} item</b></div>
                        </div>
                        @if($hasWinner)
                            @if($isNonPbf)
                                <div>
                                    <div class="sd-total-label">Kategori</div>
                                    <div class="sd-total-value" style="font-size: 0.85rem; color: #6b7280;">Beli Mandiri</div>
                                </div>
                            @else
                                <div>
                                    <div class="sd-total-label">Total harga</div>
                                    <div class="sd-total-value">Rp {{ number_format($calc['winner']['final_price'] * $res['request']['qty'], 0, ',', '.') }}</div>
                                </div>
                            @endif
                        @else
                            @php
                                $mInfo = $calc['match_info'] ?? null;
                                $topSugg = $mInfo['suggestions'][0] ?? null;
                                $hasValidSugg = $topSugg && (($topSugg['confidence'] ?? 0) >= 70);
                            @endphp
                            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.35rem;">
                                @if($hasValidSugg)
                                    <div class="sd-sugg-inline">
                                        Saran: <b>{{ $topSugg['name'] }}</b> ({{ round($topSugg['confidence']) }}%)
                                    </div>
                                @endif
                                <div style="display: flex; align-items: center; gap: 0.35rem;">
                                    <button 
                                        wire:click="mountAction('jodohkan', { raw_name: '{{ addslashes($res['request']['product_name']) }}', suggested_id: '{{ $hasValidSugg ? ($topSugg['product']['id'] ?? '') : '' }}' })"
                                        class="sd-badge-missing"
                                        style="cursor: pointer; display: flex; align-items: center; gap: 0.25rem;"
                                        title="Klik untuk mencocokkan obat secara manual"
                                    >
                                        <svg style="width: 1rem; height: 1rem;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                        </svg>
                                        Jodohkan Manual
                                    </button>
                                    <button 
                                        wire:click="mountAction('tandaiNonPbf', { raw_name: '{{ addslashes($res['request']['product_name']) }}' })"
                                        class="sd-alert-btn-nonpbf"
                                        style="padding: 0.25rem 0.5rem; font-size: 0.7rem;"
                                        title="Tandai bukan stok PBF"
                                    >
                                        🛒 Bukan PBF
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($hasWinner)
                        @if($isNonPbf)
                            <div class="sd-card-body" style="padding: 0.85rem 1.25rem; background: rgba(0,0,0,0.015);">
                                <div style="font-size: 0.775rem; color: #6b7280; line-height: 1.5;">
                                    💡 <b>Item Belanja Mandiri:</b> Produk ini ditandai sebagai non-PBF (swalayan / alkes / kosmetik). Item ini dipisahkan dari Surat Pesanan distributor farmasi dan dapat dibeli mandiri.
                                </div>
                            </div>
                        @else
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

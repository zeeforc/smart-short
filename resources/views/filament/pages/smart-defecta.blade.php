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

            // Data inisial untuk modal batch matching (Cocokkan Sekaligus)
            $batchInitialData = $unmatchedList->map(function ($item, $idx) {
                $rawName = $item['request']['product_name'];
                $qty = $item['request']['qty'];
                $info = $item['calculation']['match_info'] ?? [];
                $suggestions = $info['suggestions'] ?? [];
                $topSugg = $suggestions[0] ?? null;
                $totalCandidates = $info['total_candidates'] ?? count($suggestions);

                $formattedSuggestions = collect($suggestions)->take(4)->map(function ($s) {
                    return [
                        'id' => $s['product']['id'] ?? null,
                        'name' => $s['name'] ?? '',
                        'confidence' => round($s['confidence'] ?? 0),
                    ];
                })->values()->all();

                $defaultAction = ! empty($topSugg) ? 'match' : 'not_stocked';
                $defaultProductId = $topSugg ? ($topSugg['product']['id'] ?? null) : null;
                $defaultSelectedName = $topSugg ? ($topSugg['name'] ?? '') : '';

                return [
                    'index' => $idx + 1,
                    'card_id' => 'batch-card-' . md5($rawName),
                    'raw_name' => $rawName,
                    'qty' => $qty,
                    'action_type' => $defaultAction,
                    'normalized_product_id' => $defaultProductId,
                    'selected_name' => $defaultSelectedName,
                    'total_candidates' => $totalCandidates,
                    'suggestions' => $formattedSuggestions,
                    'reason' => $info['reason'] ?? null,
                    'search_query' => '',
                    'search_results' => [],
                    'is_searching' => false,
                    'show_search' => false,
                ];
            })->values()->all();
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

            /* Batch Action Button in Banner */
            .sd-alert-btn-batch {
                display: inline-flex;
                align-items: center;
                gap: 0.45rem;
                background: #d97706;
                color: #ffffff;
                font-size: 0.8rem;
                font-weight: 700;
                border: none;
                border-radius: 0.5rem;
                padding: 0.45rem 0.95rem;
                cursor: pointer;
                box-shadow: 0 1px 3px rgba(217, 119, 6, 0.3);
                transition: all 0.15s ease;
                white-space: nowrap;
            }
            .sd-alert-btn-batch:hover {
                background: #b45309;
                transform: translateY(-1px);
                box-shadow: 0 3px 6px rgba(217, 119, 6, 0.4);
            }

            /* Modal Backdrop */
            .sd-modal-backdrop {
                position: fixed;
                inset: 0;
                z-index: 99999;
                background: rgba(15, 23, 42, 0.75);
                backdrop-filter: blur(5px);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
            }

            /* Modal Dialog */
            .sd-modal-dialog {
                background: #ffffff;
                border-radius: 1rem;
                width: 100%;
                max-width: 58rem;
                max-height: 90vh;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
                border: 1px solid #e5e7eb;
            }
            .dark .sd-modal-dialog {
                background: #111827;
                border-color: #374151;
                color: #f9fafb;
            }

            /* Modal Header */
            .sd-modal-header {
                padding: 1.15rem 1.5rem;
                border-bottom: 1px solid #e5e7eb;
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 1rem;
                background: #fafafa;
            }
            .dark .sd-modal-header {
                background: #1f2937;
                border-color: #374151;
            }
            .sd-modal-icon-badge {
                width: 2.25rem;
                height: 2.25rem;
                border-radius: 0.625rem;
                background: #fef3c7;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .dark .sd-modal-icon-badge {
                background: rgba(245, 158, 11, 0.15);
            }
            .sd-modal-title {
                font-size: 1.05rem;
                font-weight: 700;
                color: #111827;
                margin: 0;
            }
            .dark .sd-modal-title { color: #f9fafb; }
            .sd-modal-desc {
                font-size: 0.775rem;
                color: #6b7280;
                margin: 0.25rem 0 0 0;
                line-height: 1.4;
            }
            .dark .sd-modal-desc { color: #9ca3af; }
            .sd-modal-close {
                background: transparent;
                border: none;
                color: #9ca3af;
                cursor: pointer;
                padding: 0.25rem;
                border-radius: 0.375rem;
                transition: all 0.15s ease;
            }
            .sd-modal-close:hover {
                color: #111827;
                background: #e5e7eb;
            }
            .dark .sd-modal-close:hover {
                color: #f9fafb;
                background: #374151;
            }

            /* Modal Toolbar */
            .sd-modal-toolbar {
                padding: 0.75rem 1.5rem;
                background: #f3f4f6;
                border-bottom: 1px solid #e5e7eb;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                flex-wrap: wrap;
            }
            .dark .sd-modal-toolbar {
                background: #1e293b;
                border-color: #334155;
            }
            .sd-toolbar-btn {
                font-size: 0.725rem;
                font-weight: 600;
                padding: 0.35rem 0.75rem;
                border-radius: 0.4rem;
                border: 1px solid transparent;
                cursor: pointer;
                transition: all 0.15s ease;
                white-space: nowrap;
            }
            .sd-toolbar-btn.primary {
                background: #e0e7ff;
                color: #3730a3;
                border-color: #c7d2fe;
            }
            .sd-toolbar-btn.primary:hover {
                background: #c7d2fe;
            }
            .dark .sd-toolbar-btn.primary {
                background: rgba(99, 102, 241, 0.2);
                color: #a5b4fc;
                border-color: rgba(99, 102, 241, 0.4);
            }
            .sd-toolbar-btn.secondary {
                background: #fef3c7;
                color: #92400e;
                border-color: #fde68a;
            }
            .sd-toolbar-btn.secondary:hover {
                background: #fde68a;
            }
            .dark .sd-toolbar-btn.secondary {
                background: rgba(245, 158, 11, 0.2);
                color: #fde68a;
                border-color: rgba(245, 158, 11, 0.4);
            }
            .sd-toolbar-btn.muted {
                background: #ffffff;
                color: #6b7280;
                border-color: #d1d5db;
            }
            .sd-toolbar-btn.muted:hover {
                background: #e5e7eb;
            }
            .dark .sd-toolbar-btn.muted {
                background: #374151;
                color: #d1d5db;
                border-color: #4b5563;
            }

            .sd-modal-search-wrap {
                display: flex;
                align-items: center;
                gap: 0.4rem;
                background: #ffffff;
                border: 1px solid #d1d5db;
                border-radius: 0.4rem;
                padding: 0.25rem 0.6rem;
            }
            .dark .sd-modal-search-wrap {
                background: #111827;
                border-color: #4b5563;
            }
            .sd-modal-filter-input {
                border: none;
                outline: none;
                font-size: 0.725rem;
                background: transparent;
                width: 140px;
                color: #111827;
            }
            .dark .sd-modal-filter-input {
                color: #f9fafb;
            }

            .sd-toolbar-counters {
                display: flex;
                align-items: center;
                gap: 0.35rem;
                flex-wrap: wrap;
            }
            .sd-counter-pill {
                font-size: 0.68rem;
                padding: 0.2rem 0.5rem;
                border-radius: 9999px;
                border: 1px solid transparent;
            }
            .sd-counter-pill.match {
                background: #dcfce7;
                color: #166534;
                border-color: #bbf7d0;
            }
            .dark .sd-counter-pill.match {
                background: rgba(22, 163, 74, 0.15);
                color: #4ade80;
            }
            .sd-counter-pill.nonpbf {
                background: #fef3c7;
                color: #92400e;
                border-color: #fde68a;
            }
            .dark .sd-counter-pill.nonpbf {
                background: rgba(245, 158, 11, 0.15);
                color: #fbbf24;
            }
            .sd-counter-pill.unlisted {
                background: #f3e8ff;
                color: #6b21a8;
                border-color: #e9d5ff;
            }
            .dark .sd-counter-pill.unlisted {
                background: rgba(168, 85, 247, 0.15);
                color: #c084fc;
            }
            .sd-counter-pill.skip {
                background: #e5e7eb;
                color: #4b5563;
            }
            .dark .sd-counter-pill.skip {
                background: #374151;
                color: #9ca3af;
            }

            /* Modal Body */
            .sd-modal-body {
                flex: 1;
                overflow-y: auto;
                padding: 1.25rem 1.5rem;
                display: flex;
                flex-direction: column;
                gap: 1rem;
                background: #f8fafc;
            }
            .dark .sd-modal-body {
                background: #0f172a;
            }

            /* Card in Batch List */
            .sd-batch-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.75rem;
                padding: 1rem 1.15rem;
                transition: all 0.2s ease;
            }
            .dark .sd-batch-card {
                background: #1e293b;
                border-color: #334155;
            }
            .sd-batch-card.status-match {
                border-color: #86efac;
                box-shadow: 0 0 0 1px #86efac;
            }
            .dark .sd-batch-card.status-match {
                border-color: rgba(34, 197, 94, 0.5);
                box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.4);
            }
            .sd-batch-card.status-nonpbf {
                border-color: #fde68a;
            }
            .dark .sd-batch-card.status-nonpbf {
                border-color: rgba(245, 158, 11, 0.4);
            }
            .sd-batch-card.status-unlisted {
                border-color: #d8b4fe;
            }
            .dark .sd-batch-card.status-unlisted {
                border-color: rgba(168, 85, 247, 0.4);
            }
            .sd-batch-card.status-skip {
                opacity: 0.8;
            }

            /* Highlight Animation */
            .sd-highlight-pulse {
                animation: sdPulse 1s ease-in-out 3 alternate;
            }
            @keyframes sdPulse {
                0% { box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.7); }
                100% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0.1); }
            }

            .sd-batch-card-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                margin-bottom: 0.75rem;
                flex-wrap: wrap;
            }
            .sd-batch-card-num {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 1.35rem;
                height: 1.35rem;
                border-radius: 9999px;
                background: #f1f5f9;
                color: #475569;
                font-size: 0.7rem;
                font-weight: 700;
            }
            .dark .sd-batch-card-num {
                background: #334155;
                color: #cbd5e1;
            }
            .sd-batch-card-name {
                font-size: 0.9rem;
                font-weight: 700;
                color: #0f172a;
            }
            .dark .sd-batch-card-name { color: #f8fafc; }
            .sd-batch-card-qty {
                font-size: 0.75rem;
                color: #64748b;
            }
            .sd-batch-card-badge {
                font-size: 0.7rem;
                font-weight: 600;
                padding: 0.15rem 0.5rem;
                border-radius: 9999px;
            }
            .sd-batch-card-badge.sugg {
                background: #ecfdf5;
                color: #047857;
                border: 1px solid #a7f3d0;
            }
            .dark .sd-batch-card-badge.sugg {
                background: rgba(16, 185, 129, 0.15);
                color: #34d399;
                border-color: rgba(16, 185, 129, 0.3);
            }
            .sd-batch-card-badge.none {
                background: #fffbeb;
                color: #b45309;
                border: 1px solid #fde68a;
            }
            .dark .sd-batch-card-badge.none {
                background: rgba(245, 158, 11, 0.15);
                color: #fbbf24;
                border-color: rgba(245, 158, 11, 0.3);
            }

            /* Segmented Options Grid */
            .sd-segmented-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 0.4rem;
                margin-bottom: 0.75rem;
            }
            @media (max-width: 768px) {
                .sd-segmented-grid { grid-template-columns: repeat(2, 1fr); }
            }
            .sd-segmented-option {
                display: flex;
                align-items: center;
                gap: 0.4rem;
                padding: 0.45rem 0.65rem;
                font-size: 0.725rem;
                font-weight: 600;
                background: #f8fafc;
                border: 1px solid #cbd5e1;
                border-radius: 0.5rem;
                cursor: pointer;
                color: #475569;
                transition: all 0.15s ease;
                text-align: left;
            }
            .dark .sd-segmented-option {
                background: #0f172a;
                border-color: #334155;
                color: #94a3b8;
            }
            .sd-segmented-option:hover {
                background: #f1f5f9;
            }
            .dark .sd-segmented-option:hover {
                background: #1e293b;
            }
            .sd-radio-dot {
                width: 0.65rem;
                height: 0.65rem;
                border-radius: 9999px;
                border: 2px solid #94a3b8;
                flex-shrink: 0;
            }
            .sd-segmented-option.active.match {
                background: #f0fdf4;
                border-color: #22c55e;
                color: #15803d;
            }
            .dark .sd-segmented-option.active.match {
                background: rgba(34, 197, 94, 0.15);
                border-color: #22c55e;
                color: #4ade80;
            }
            .sd-segmented-option.active.match .sd-radio-dot {
                background: #22c55e;
                border-color: #22c55e;
            }

            .sd-segmented-option.active.nonpbf {
                background: #fffbeb;
                border-color: #f59e0b;
                color: #b45309;
            }
            .dark .sd-segmented-option.active.nonpbf {
                background: rgba(245, 158, 11, 0.15);
                border-color: #f59e0b;
                color: #fbbf24;
            }
            .sd-segmented-option.active.nonpbf .sd-radio-dot {
                background: #f59e0b;
                border-color: #f59e0b;
            }

            .sd-segmented-option.active.unlisted {
                background: #faf5ff;
                border-color: #a855f7;
                color: #7e22ce;
            }
            .dark .sd-segmented-option.active.unlisted {
                background: rgba(168, 85, 247, 0.15);
                border-color: #a855f7;
                color: #c084fc;
            }
            .sd-segmented-option.active.unlisted .sd-radio-dot {
                background: #a855f7;
                border-color: #a855f7;
            }

            .sd-segmented-option.active.skip {
                background: #e2e8f0;
                border-color: #94a3b8;
                color: #334155;
            }
            .dark .sd-segmented-option.active.skip {
                background: #334155;
                border-color: #64748b;
                color: #e2e8f0;
            }
            .sd-segmented-option.active.skip .sd-radio-dot {
                background: #64748b;
                border-color: #64748b;
            }

            /* Match Panel */
            .sd-batch-match-panel {
                background: #f0fdf4;
                border: 1px solid #bbf7d0;
                border-radius: 0.625rem;
                padding: 0.75rem 1rem;
            }
            .dark .sd-batch-match-panel {
                background: rgba(22, 163, 74, 0.08);
                border-color: rgba(22, 163, 74, 0.25);
            }
            .sd-panel-label {
                font-size: 0.7rem;
                font-weight: 700;
                color: #166534;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                margin-bottom: 0.35rem;
            }
            .dark .sd-panel-label { color: #86efac; }
            .sd-chips-list {
                display: flex;
                flex-wrap: wrap;
                gap: 0.35rem;
            }
            .sd-chip-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                font-size: 0.75rem;
                padding: 0.3rem 0.65rem;
                border-radius: 9999px;
                background: #ffffff;
                border: 1px solid #86efac;
                color: #166534;
                cursor: pointer;
                transition: all 0.15s ease;
                font-weight: 500;
            }
            .dark .sd-chip-btn {
                background: #1e293b;
                border-color: rgba(34, 197, 94, 0.35);
                color: #86efac;
            }
            .sd-chip-btn:hover {
                border-color: #22c55e;
                background: #ecfdf5;
            }
            .sd-chip-btn.selected {
                background: #22c55e;
                border-color: #16a34a;
                color: #ffffff;
                font-weight: 700;
                box-shadow: 0 1px 3px rgba(34, 197, 94, 0.3);
            }
            .sd-chip-btn.selected .sd-chip-confidence {
                background: rgba(255, 255, 255, 0.25);
                color: #ffffff;
            }
            .sd-chip-confidence {
                font-size: 0.65rem;
                font-weight: 700;
                padding: 0.05rem 0.35rem;
                border-radius: 9999px;
                background: #dcfce7;
                color: #166534;
            }

            .sd-selected-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                flex-wrap: wrap;
                padding-top: 0.35rem;
            }
            .sd-selected-info {
                display: flex;
                align-items: center;
                gap: 0.4rem;
                flex-wrap: wrap;
                font-size: 0.8rem;
            }
            .sd-selected-label {
                color: #4b5563;
                font-weight: 600;
            }
            .dark .sd-selected-label { color: #9ca3af; }
            .sd-selected-value {
                color: #15803d;
                font-weight: 700;
                background: #dcfce7;
                padding: 0.15rem 0.55rem;
                border-radius: 0.375rem;
                border: 1px solid #bbf7d0;
            }
            .dark .sd-selected-value {
                background: rgba(34, 197, 94, 0.2);
                color: #4ade80;
                border-color: rgba(34, 197, 94, 0.35);
            }
            .sd-selected-empty {
                color: #dc2626;
                font-style: italic;
                font-size: 0.75rem;
            }
            .sd-btn-search-toggle {
                font-size: 0.725rem;
                font-weight: 600;
                color: #4338ca;
                background: #e0e7ff;
                border: 1px solid #c7d2fe;
                border-radius: 0.375rem;
                padding: 0.25rem 0.6rem;
                cursor: pointer;
                transition: all 0.15s ease;
            }
            .sd-btn-search-toggle:hover {
                background: #c7d2fe;
            }
            .dark .sd-btn-search-toggle {
                background: rgba(99, 102, 241, 0.2);
                color: #a5b4fc;
                border-color: rgba(99, 102, 241, 0.35);
            }

            .sd-live-search-panel {
                margin-top: 0.65rem;
                background: #ffffff;
                border: 1px solid #cbd5e1;
                border-radius: 0.5rem;
                padding: 0.65rem;
            }
            .dark .sd-live-search-panel {
                background: #0f172a;
                border-color: #334155;
            }
            .sd-search-master-input {
                width: 100%;
                padding: 0.4rem 0.65rem;
                font-size: 0.775rem;
                border: 1px solid #d1d5db;
                border-radius: 0.375rem;
                outline: none;
                background: #ffffff;
                color: #111827;
            }
            .dark .sd-search-master-input {
                background: #1e293b;
                border-color: #475569;
                color: #f8fafc;
            }
            .sd-search-master-input:focus {
                border-color: #4f46e5;
                box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
            }
            .sd-search-spinner {
                position: absolute;
                right: 0.75rem;
                top: 50%;
                transform: translateY(-50%);
                font-size: 0.7rem;
                color: #6b7280;
            }
            .sd-search-results-list {
                margin-top: 0.5rem;
                max-height: 160px;
                overflow-y: auto;
                border: 1px solid #e2e8f0;
                border-radius: 0.375rem;
                background: #ffffff;
            }
            .dark .sd-search-results-list {
                background: #1e293b;
                border-color: #334155;
            }
            .sd-search-result-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0.4rem 0.65rem;
                font-size: 0.75rem;
                color: #1e293b;
                border-bottom: 1px solid #f1f5f9;
                cursor: pointer;
                transition: background 0.1s ease;
            }
            .dark .sd-search-result-item {
                color: #f1f5f9;
                border-bottom-color: #334155;
            }
            .sd-search-result-item:hover {
                background: #e0e7ff;
            }
            .dark .sd-search-result-item:hover {
                background: rgba(99, 102, 241, 0.2);
            }
            .sd-select-hint {
                font-size: 0.68rem;
                color: #4f46e5;
                font-weight: 600;
            }
            .dark .sd-select-hint { color: #a5b4fc; }
            .sd-search-no-result {
                padding: 0.5rem;
                font-size: 0.725rem;
                color: #64748b;
                font-style: italic;
                text-align: center;
            }

            .sd-batch-notice {
                font-size: 0.75rem;
                padding: 0.5rem 0.75rem;
                border-radius: 0.5rem;
                margin-top: 0.25rem;
            }
            .sd-batch-notice.nonpbf {
                background: #fffbeb;
                color: #92400e;
                border: 1px solid #fde68a;
            }
            .dark .sd-batch-notice.nonpbf {
                background: rgba(245, 158, 11, 0.1);
                color: #fde68a;
                border-color: rgba(245, 158, 11, 0.25);
            }
            .sd-batch-notice.unlisted {
                background: #faf5ff;
                color: #6b21a8;
                border: 1px solid #e9d5ff;
            }
            .dark .sd-batch-notice.unlisted {
                background: rgba(168, 85, 247, 0.1);
                color: #e9d5ff;
                border-color: rgba(168, 85, 247, 0.25);
            }
            .sd-batch-notice.skip {
                background: #f1f5f9;
                color: #475569;
                border: 1px solid #e2e8f0;
            }
            .dark .sd-batch-notice.skip {
                background: #1e293b;
                color: #94a3b8;
                border-color: #334155;
            }

            .sd-empty-filter {
                padding: 2rem;
                text-align: center;
                font-size: 0.85rem;
                color: #64748b;
            }

            /* Modal Footer */
            .sd-modal-footer {
                padding: 1rem 1.5rem;
                border-top: 1px solid #e5e7eb;
                background: #ffffff;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                flex-wrap: wrap;
            }
            .dark .sd-modal-footer {
                background: #111827;
                border-color: #374151;
            }
            .sd-footer-summary {
                font-size: 0.8rem;
                color: #4b5563;
            }
            .dark .sd-footer-summary { color: #9ca3af; }
            .sd-modal-cancel-btn {
                background: #f3f4f6;
                color: #374151;
                font-size: 0.8rem;
                font-weight: 600;
                border: 1px solid #d1d5db;
                border-radius: 0.5rem;
                padding: 0.55rem 1rem;
                cursor: pointer;
                transition: all 0.15s ease;
            }
            .sd-modal-cancel-btn:hover { background: #e5e7eb; }
            .dark .sd-modal-cancel-btn {
                background: #374151;
                color: #d1d5db;
                border-color: #4b5563;
            }
            .sd-modal-save-btn {
                background: #059669;
                color: #ffffff;
                font-size: 0.825rem;
                font-weight: 700;
                border: none;
                border-radius: 0.5rem;
                padding: 0.55rem 1.25rem;
                cursor: pointer;
                box-shadow: 0 2px 4px rgba(5, 150, 105, 0.3);
                transition: all 0.15s ease;
            }
            .sd-modal-save-btn:hover {
                background: #047857;
                transform: translateY(-1px);
                box-shadow: 0 4px 8px rgba(5, 150, 105, 0.4);
            }
            .sd-modal-save-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
                transform: none;
                box-shadow: none;
            }
        </style>

        <script>
            window.smartDefectaHub = function(config) {
                return {
                    perPage: config.perPage || 10,
                    currentPage: 1,
                    total: config.total || 0,
                    get totalPages() { return Math.ceil(this.total / this.perPage) || 1; },
                    get start() { return (this.currentPage - 1) * this.perPage; },
                    get end() { return Math.min(this.currentPage * this.perPage, this.total); },
                    isVisible(index) { return index >= this.start && index < this.end; },
                    pages() {
                        let p = [];
                        for (let i = 1; i <= this.totalPages; i++) p.push(i);
                        return p;
                    },

                    // Modal State
                    isModalOpen: false,
                    modalFilter: '',
                    isSubmitting: false,
                    items: config.initialBatch || [],

                    openModal(cardId = null) {
                        this.isModalOpen = true;
                        this.modalFilter = '';
                        document.body.classList.add('overflow-hidden');

                        if (cardId) {
                            this.$nextTick(() => {
                                const el = document.getElementById(cardId);
                                if (el) {
                                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    el.classList.add('sd-highlight-pulse');
                                    setTimeout(() => el.classList.remove('sd-highlight-pulse'), 2500);
                                }
                            });
                        }
                    },

                    closeModal() {
                        this.isModalOpen = false;
                        document.body.classList.remove('overflow-hidden');
                    },

                    filteredItems() {
                        if (!this.modalFilter || this.modalFilter.trim() === '') {
                            return this.items;
                        }
                        const q = this.modalFilter.toLowerCase().trim();
                        return this.items.filter(item => {
                            const raw = (item.raw_name || '').toLowerCase();
                            const sel = (item.selected_name || '').toLowerCase();
                            return raw.includes(q) || sel.includes(q);
                        });
                    },

                    countMatched() {
                        return this.items.filter(i => i.action_type === 'match' && i.normalized_product_id).length;
                    },
                    countNotStocked() {
                        return this.items.filter(i => i.action_type === 'not_stocked').length;
                    },
                    countUnlisted() {
                        return this.items.filter(i => i.action_type === 'unlisted').length;
                    },
                    countSkipped() {
                        return this.items.filter(i => i.action_type === 'skip' || (i.action_type === 'match' && !i.normalized_product_id)).length;
                    },

                    applyAllSuggestions() {
                        this.items.forEach(item => {
                            if (item.suggestions && item.suggestions.length > 0) {
                                item.action_type = 'match';
                                item.normalized_product_id = item.suggestions[0].id;
                                item.selected_name = item.suggestions[0].name;
                            }
                        });
                    },

                    markAllEmptyAsNonPbf() {
                        this.items.forEach(item => {
                            if (!item.suggestions || item.suggestions.length === 0) {
                                item.action_type = 'not_stocked';
                            }
                        });
                    },

                    setAllToSkip() {
                        this.items.forEach(item => {
                            item.action_type = 'skip';
                        });
                    },

                    selectSuggestion(item, sugg) {
                        item.action_type = 'match';
                        item.normalized_product_id = sugg.id;
                        item.selected_name = sugg.name;
                    },

                    async searchMasterProduct(item) {
                        const q = item.search_query;
                        if (!q || q.trim().length < 2) {
                            item.search_results = [];
                            return;
                        }
                        item.is_searching = true;
                        try {
                            const res = await this.$wire.searchNormalizedProducts(q);
                            item.search_results = res || [];
                        } catch (err) {
                            console.error(err);
                        } finally {
                            item.is_searching = false;
                        }
                    },

                    chooseSearchResult(item, prod) {
                        item.action_type = 'match';
                        item.normalized_product_id = prod.id;
                        item.selected_name = prod.name;
                        item.search_results = [];
                        item.show_search = false;
                        item.search_query = '';
                    },

                    async submitBatchDecisions() {
                        const toProcess = this.items.filter(i => i.action_type !== 'skip');
                        if (toProcess.length === 0) {
                            alert('Pilih setidaknya 1 obat untuk dicocokkan atau ditandai bukan PBF.');
                            return;
                        }
                        this.isSubmitting = true;
                        try {
                            const payload = this.items.map(i => ({
                                raw_name: i.raw_name,
                                action_type: i.action_type,
                                normalized_product_id: i.normalized_product_id
                            }));
                            await this.$wire.saveBatchMatching(payload);
                            this.closeModal();
                        } catch (err) {
                            console.error(err);
                        } finally {
                            this.isSubmitting = false;
                        }
                    }
                };
            };

            if (window.Alpine) {
                window.Alpine.data('smartDefectaHub', (config) => window.smartDefectaHub(config));
            } else {
                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('smartDefectaHub', (config) => window.smartDefectaHub(config));
                });
            }
        </script>

        <div class="sd-section" x-data="smartDefectaHub({
            perPage: 10,
            total: {{ $totalItems }},
            initialBatch: {{ \Illuminate\Support\Js::from($batchInitialData) }}
        })">
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
                        <button 
                            type="button"
                            @click="openModal()"
                            class="sd-alert-btn-batch"
                            title="Buka modal untuk mencocokkan semua obat sekaligus"
                        >
                            <svg style="width: 1.1rem; height: 1.1rem;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                            </svg>
                            <span>Cocokkan Sekaligus ({{ $missingItems }} Obat)</span>
                        </button>
                    </div>
                    <div class="sd-alert-items">
                        @foreach($unmatchedList as $unmatched)
                            @php
                                $uName = $unmatched['request']['product_name'];
                                $uQty = $unmatched['request']['qty'];
                                $uInfo = $unmatched['calculation']['match_info'] ?? null;
                                $topSugg = $uInfo['suggestions'][0] ?? null;
                                $totalCandidates = $uInfo['total_candidates'] ?? count($uInfo['suggestions'] ?? []);
                                $candidateIds = collect($uInfo['suggestions'] ?? [])
                                    ->pluck('product.id')
                                    ->filter()
                                    ->implode(',');
                            @endphp
                            <div class="sd-alert-row">
                                <div class="sd-alert-row-left">
                                    <span class="sd-alert-badge-num">{{ $loop->iteration }}</span>
                                    <div>
                                        <span class="sd-alert-drug">{{ $uName }}</span>
                                        <span class="sd-alert-qty">({{ $uQty }} item)</span>
                                        @if($topSugg)
                                            <div class="sd-alert-sugg-text" style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; margin-top: 0.2rem;">
                                                💡 @if($totalCandidates > 1)
                                                    <span style="display: inline-flex; align-items: center; background: #e0e7ff; color: #3730a3; padding: 0.1rem 0.5rem; border-radius: 9999px; font-weight: 700; font-size: 0.72rem;">
                                                        Ditemukan {{ $totalCandidates }} produk cocok
                                                    </span>
                                                @endif
                                                <span>Rekomendasi terdekat: <strong>{{ $topSugg['name'] }}</strong> (Akurasi: {{ round($topSugg['confidence']) }}%)</span>
                                            </div>
                                        @else
                                            <div class="sd-alert-none-text">
                                                {{ $uInfo['reason'] ?? 'Tidak ditemukan produk di pricelist supplier manapun' }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0;">
                                    <button 
                                        type="button"
                                        @click="openModal('{{ 'batch-card-' . md5($uName) }}')"
                                        class="sd-alert-action-btn"
                                        title="Buka modal dan fokus ke obat ini"
                                    >
                                        Cocokkan Manual
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
                                $totalCandidates = $mInfo['total_candidates'] ?? count($mInfo['suggestions'] ?? []);
                                $hasValidSugg = ! empty($topSugg);
                                $candidateIds = collect($mInfo['suggestions'] ?? [])
                                    ->pluck('product.id')
                                    ->filter()
                                    ->implode(',');
                            @endphp
                            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.35rem;">
                                @if($hasValidSugg)
                                    <div class="sd-sugg-inline" style="display: flex; align-items: center; gap: 0.35rem;">
                                        @if($totalCandidates > 1)
                                            <span style="display: inline-block; background: #e0e7ff; color: #3730a3; padding: 0.05rem 0.4rem; border-radius: 9999px; font-weight: 700; font-size: 0.68rem;">
                                                {{ $totalCandidates }} produk cocok
                                            </span>
                                        @endif
                                        <span>Saran: <b>{{ $topSugg['name'] }}</b> ({{ round($topSugg['confidence']) }}%)</span>
                                    </div>
                                @endif
                                <div style="display: flex; align-items: center; gap: 0.35rem;">
                                    <button 
                                        type="button"
                                        @click="openModal('{{ 'batch-card-' . md5($res['request']['product_name']) }}')"
                                        class="sd-badge-missing"
                                        style="cursor: pointer; display: flex; align-items: center; gap: 0.25rem;"
                                        title="Klik untuk membuka modal dan mencocokkan obat"
                                    >
                                        <svg style="width: 1rem; height: 1rem;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                        </svg>
                                        Cocokkan Manual
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

            {{-- Modal Cocokkan Sekaligus (Batch Match Modal) --}}
            <div 
                x-show="isModalOpen" 
                x-cloak 
                class="sd-modal-backdrop"
                @keydown.escape.window="closeModal()"
            >
                <div class="sd-modal-dialog" @click.away="closeModal()">
                    {{-- Modal Header --}}
                    <div class="sd-modal-header">
                        <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                            <div class="sd-modal-icon-badge">
                                <svg style="width: 1.35rem; height: 1.35rem; color: #d97706;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="sd-modal-title">Cocokkan Obat Defecta Sekaligus</h3>
                                <p class="sd-modal-desc">
                                    Ada <span style="font-weight: 700;" x-text="items.length"></span> obat butuh perhatian. Rekomendasi terdekat telah otomatis dipilihkan untuk Anda. Anda bisa scroll, sesuaikan pilihan, dan simpan semuanya sekaligus dalam satu klik.
                                </p>
                            </div>
                        </div>
                        <button type="button" class="sd-modal-close" @click="closeModal()" title="Tutup">
                            <svg style="width: 1.25rem; height: 1.25rem;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Modal Toolbar --}}
                    <div class="sd-modal-toolbar">
                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <button type="button" class="sd-toolbar-btn primary" @click="applyAllSuggestions()">
                                ⚡ Terapkan Semua Rekomendasi
                            </button>
                            <button type="button" class="sd-toolbar-btn secondary" @click="markAllEmptyAsNonPbf()">
                                🛒 Set Sisa (Tanpa Saran) Bukan PBF
                            </button>
                            <button type="button" class="sd-toolbar-btn muted" @click="setAllToSkip()">
                                ↺ Reset / Lewati Semua
                            </button>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                            <div class="sd-modal-search-wrap">
                                <svg style="width: 0.9rem; height: 0.9rem; color: #9ca3af;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                                <input 
                                    type="text" 
                                    x-model="modalFilter" 
                                    placeholder="Filter nama obat di sini..." 
                                    class="sd-modal-filter-input"
                                />
                            </div>
                            <div class="sd-toolbar-counters">
                                <span class="sd-counter-pill match" title="Akan dicocokkan ke master PBF">
                                    🔗 <strong x-text="countMatched()"></strong> Cocok
                                </span>
                                <span class="sd-counter-pill nonpbf" title="Akan ditandai belanja mandiri (bukan PBF)">
                                    🛒 <strong x-text="countNotStocked()"></strong> Bukan PBF
                                </span>
                                <span class="sd-counter-pill unlisted" title="Akan ditandai belum dijual distributor">
                                    📦 <strong x-text="countUnlisted()"></strong> Belum Ada PBF
                                </span>
                                <span class="sd-counter-pill skip" title="Dilewati (tidak diubah)">
                                    ⏭️ <strong x-text="countSkipped()"></strong> Lewati
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Body (Scrollable Cards) --}}
                    <div class="sd-modal-body">
                        <template x-for="(item, idx) in filteredItems()" :key="item.card_id">
                            <div 
                                :id="item.card_id"
                                class="sd-batch-card"
                                :class="{
                                    'status-match': item.action_type === 'match',
                                    'status-nonpbf': item.action_type === 'not_stocked',
                                    'status-unlisted': item.action_type === 'unlisted',
                                    'status-skip': item.action_type === 'skip'
                                }"
                            >
                                {{-- Card Header --}}
                                <div class="sd-batch-card-header">
                                    <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                                        <span class="sd-batch-card-num" x-text="item.index"></span>
                                        <span class="sd-batch-card-name" x-text="item.raw_name"></span>
                                        <span class="sd-batch-card-qty" x-text="'(' + item.qty + ' item)'"></span>
                                    </div>
                                    <div>
                                        <template x-if="item.suggestions && item.suggestions.length > 0">
                                            <span class="sd-batch-card-badge sugg">
                                                💡 Ditemukan <span x-text="item.total_candidates"></span> produk cocok (Top: <span x-text="item.suggestions[0].confidence + '%'"></span>)
                                            </span>
                                        </template>
                                        <template x-if="!item.suggestions || item.suggestions.length === 0">
                                            <span class="sd-batch-card-badge none">
                                                ⚠️ Belum ada produk cocok di distributor
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                {{-- Segmented Controls / Decision Buttons --}}
                                <div class="sd-segmented-grid">
                                    <button 
                                        type="button" 
                                        class="sd-segmented-option" 
                                        :class="{ 'active match': item.action_type === 'match' }"
                                        @click="item.action_type = 'match'"
                                    >
                                        <span class="sd-radio-dot"></span>
                                        <span>Cocokkan Master PBF</span>
                                    </button>
                                    <button 
                                        type="button" 
                                        class="sd-segmented-option" 
                                        :class="{ 'active nonpbf': item.action_type === 'not_stocked' }"
                                        @click="item.action_type = 'not_stocked'"
                                    >
                                        <span class="sd-radio-dot"></span>
                                        <span>Bukan Stok PBF (Belanja Mandiri)</span>
                                    </button>
                                    <button 
                                        type="button" 
                                        class="sd-segmented-option" 
                                        :class="{ 'active unlisted': item.action_type === 'unlisted' }"
                                        @click="item.action_type = 'unlisted'"
                                    >
                                        <span class="sd-radio-dot"></span>
                                        <span>Belum Dijual PBF Rekanan</span>
                                    </button>
                                    <button 
                                        type="button" 
                                        class="sd-segmented-option" 
                                        :class="{ 'active skip': item.action_type === 'skip' }"
                                        @click="item.action_type = 'skip'"
                                    >
                                        <span class="sd-radio-dot"></span>
                                        <span>Lewati (Belum Putus)</span>
                                    </button>
                                </div>

                                {{-- Details for Match --}}
                                <template x-if="item.action_type === 'match'">
                                    <div class="sd-batch-match-panel">
                                        {{-- Recommendations chips --}}
                                        <template x-if="item.suggestions && item.suggestions.length > 0">
                                            <div style="margin-bottom: 0.75rem;">
                                                <div class="sd-panel-label">Rekomendasi Distributor (Klik untuk memilih):</div>
                                                <div class="sd-chips-list">
                                                    <template x-for="sugg in item.suggestions" :key="sugg.id">
                                                        <button 
                                                            type="button" 
                                                            class="sd-chip-btn"
                                                            :class="{ 'selected': item.normalized_product_id == sugg.id }"
                                                            @click="selectSuggestion(item, sugg)"
                                                        >
                                                            <span x-show="item.normalized_product_id == sugg.id" style="color: #059669; font-weight: bold;">✓</span>
                                                            <span x-text="sugg.name"></span>
                                                            <span class="sd-chip-confidence" x-text="sugg.confidence + '%'"></span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Selected Product Indicator & Search Trigger --}}
                                        <div class="sd-selected-row">
                                            <div class="sd-selected-info">
                                                <span class="sd-selected-label">Master Terpilih:</span>
                                                <template x-if="item.selected_name">
                                                    <span class="sd-selected-value" x-text="item.selected_name"></span>
                                                </template>
                                                <template x-if="!item.selected_name">
                                                    <span class="sd-selected-empty">Belum ada master yang dipilih — silakan pilih saran di atas atau cari manual</span>
                                                </template>
                                            </div>
                                            <button 
                                                type="button" 
                                                class="sd-btn-search-toggle"
                                                @click="item.show_search = !item.show_search"
                                                x-text="item.show_search ? '✕ Tutup Pencarian' : '🔍 Cari Master Lain...'"
                                            ></button>
                                        </div>

                                        {{-- Live Search Box --}}
                                        <div x-show="item.show_search" class="sd-live-search-panel" x-cloak>
                                            <div style="position: relative;">
                                                <input 
                                                    type="text" 
                                                    x-model="item.search_query"
                                                    @input.debounce.300ms="searchMasterProduct(item)"
                                                    placeholder="Ketik nama obat di master PBF (min. 2 huruf)..."
                                                    class="sd-search-master-input"
                                                />
                                                <div x-show="item.is_searching" class="sd-search-spinner" x-cloak>
                                                    Mencari...
                                                </div>
                                            </div>

                                            {{-- Dropdown Results --}}
                                            <div x-show="item.search_results && item.search_results.length > 0" class="sd-search-results-list" x-cloak>
                                                <template x-for="res in item.search_results" :key="res.id">
                                                    <div 
                                                        class="sd-search-result-item"
                                                        @click="chooseSearchResult(item, res)"
                                                    >
                                                        <span x-text="res.name"></span>
                                                        <span class="sd-select-hint">Pilih ↵</span>
                                                    </div>
                                                </template>
                                            </div>
                                            <div x-show="item.search_query && item.search_query.length >= 2 && (!item.search_results || item.search_results.length === 0) && !item.is_searching" class="sd-search-no-result" x-cloak>
                                                Tidak ditemukan produk dengan kata kunci tersebut.
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                {{-- Details for Not Stocked --}}
                                <template x-if="item.action_type === 'not_stocked'">
                                    <div class="sd-batch-notice nonpbf">
                                        🛒 Barang ini akan ditandai sebagai <strong>Belanja Mandiri / Swalayan / Non-PBF</strong> dan dipisahkan dari Surat Pesanan distributor.
                                    </div>
                                </template>

                                {{-- Details for Unlisted --}}
                                <template x-if="item.action_type === 'unlisted'">
                                    <div class="sd-batch-notice unlisted">
                                        📦 Barang ini akan ditandai sebagai <strong>Belum Tersedia di PBF Rekanan Manapun</strong>.
                                    </div>
                                </template>

                                {{-- Details for Skip --}}
                                <template x-if="item.action_type === 'skip'">
                                    <div class="sd-batch-notice skip">
                                        ⏭️ Item ini akan dilewati dan tidak akan disimpan atau diubah sekarang.
                                    </div>
                                </template>
                            </div>
                        </template>

                        <div x-show="filteredItems().length === 0" class="sd-empty-filter" x-cloak>
                            Tidak ada obat yang cocok dengan filter pencarian "<span x-text="modalFilter"></span>".
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="sd-modal-footer">
                        <div class="sd-footer-summary">
                            <span style="font-weight: 600; color: #111827;" class="dark:text-white">Siap diproses:</span>
                            <span style="color: #059669; font-weight: 700;" x-text="countMatched() + ' dicocokkan'"></span>, 
                            <span style="color: #d97706; font-weight: 700;" x-text="countNotStocked() + ' non-PBF'"></span>, 
                            <span style="color: #7c3aed; font-weight: 700;" x-text="countUnlisted() + ' belum ada PBF'"></span>
                            <span style="color: #9ca3af;" x-text="'(' + countSkipped() + ' dilewati)'"></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <button type="button" class="sd-modal-cancel-btn" @click="closeModal()" :disabled="isSubmitting">
                                Batal
                            </button>
                            <button 
                                type="button" 
                                class="sd-modal-save-btn" 
                                @click="submitBatchDecisions()" 
                                :disabled="isSubmitting || (countMatched() + countNotStocked() + countUnlisted() === 0)"
                            >
                                <span x-show="!isSubmitting">
                                    Simpan & Terapkan (<span x-text="countMatched() + countNotStocked() + countUnlisted()"></span> Obat)
                                </span>
                                <span x-show="isSubmitting" style="display: flex; align-items: center; gap: 0.4rem;" x-cloak>
                                    <svg style="width: 1rem; height: 1rem; animation: spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Menyimpan & Menghitung...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
        </div>
    @endif
</x-filament-panels::page>

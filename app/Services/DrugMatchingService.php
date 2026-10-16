<?php

namespace App\Services;

use App\Models\NormalizedProduct;
use App\Models\ProductAlias;
use Illuminate\Support\Collection;

class DrugMatchingService
{
    protected ?AiDrugMatchingService $aiService;

    public function __construct(
        protected ProductNormalizationService $normalizer,
        ?AiDrugMatchingService $aiService = null
    ) {
        $this->aiService = $aiService ?? app(AiDrugMatchingService::class);
    }

    /**
     * @return array{
     *     status: string,
     *     product: ?NormalizedProduct,
     *     confidence: float,
     *     matched_name: ?string,
     *     suggestions: array,
     *     reason: ?string
     * }
     */
    public function match(string $rawName, bool $useAi = false): array
    {
        $cleanRaw = mb_strtolower(trim($rawName));
        $parsed = $this->normalizer->parseDrugName($rawName, '');
        $canonical = $parsed['canonical'];
        $reqName = $parsed['name'];
        $reqStrength = $parsed['strength'];
        $reqForm = $parsed['form'];

        // 1. Cek Alias Terverifikasi (Kamus Manual / Tersimpan Sebelumnya)
        $alias = ProductAlias::where('alias_normalized', $canonical)
            ->orWhere('alias_raw', $rawName)
            ->orWhere('alias_raw', $cleanRaw)
            ->first();

        if ($alias) {
            if ($alias->status === 'not_stocked') {
                return [
                    'status' => 'not_stocked',
                    'product' => null,
                    'confidence' => 100.0,
                    'matched_name' => 'Bukan Stok PBF (Belanja Mandiri)',
                    'suggestions' => [],
                    'reason' => 'Ditandai sebagai belanja mandiri / non-PBF',
                ];
            }

            if ($alias->status === 'unlisted') {
                return [
                    'status' => 'unlisted',
                    'product' => null,
                    'confidence' => 100.0,
                    'matched_name' => 'Belum Ada di PBF Rekanan',
                    'suggestions' => [],
                    'reason' => 'Obat belum tersedia di pricelist distributor rekanan manapun',
                ];
            }

            $product = $alias->normalizedProduct;

            // Jika relasi ID terputus (misal karena pricelist di-reimport sehingga ID master berubah),
            // pulihkan secara otomatis menggunakan target_canonical permanen!
            if (! $product && ! empty($alias->target_canonical)) {
                $product = NormalizedProduct::where('normalized_name', $alias->target_canonical)->first();
                if ($product) {
                    $alias->update(['normalized_product_id' => $product->id]);
                }
            }

            if ($product) {
                // Backfill target_canonical jika sebelumnya belum tersimpan
                if (empty($alias->target_canonical) && ! empty($product->normalized_name)) {
                    $alias->update(['target_canonical' => $product->normalized_name]);
                }

                return [
                    'status' => 'alias',
                    'product' => $product,
                    'confidence' => 100.0,
                    'matched_name' => trim("{$product->parsed_name} {$product->parsed_strength}"),
                    'suggestions' => [],
                    'reason' => null,
                ];
            }
        }

        // 2. Cek Exact Match (Nama + Kekuatan Dosis Terstruktur)
        $exactQuery = NormalizedProduct::query();
        if (! empty($reqStrength)) {
            $exactQuery->where('parsed_name', $reqName)
                ->where('parsed_strength', $reqStrength);
        } else {
            $exactQuery->where('parsed_name', $reqName);
        }

        $exact = $exactQuery->first();
        if (! $exact && ! empty($canonical)) {
            $exact = NormalizedProduct::where('normalized_name', $canonical)->first();
        }

        if (! $exact) {
            $nameWithoutForm = trim(preg_replace('/\b(TAB|KAP|SYR|CR|INJ|DROP|OINT|SUSP)\b/i', '', $reqName));
            $nameWithoutForm = trim(preg_replace('/\s+/', ' ', $nameWithoutForm));
            if (! empty($nameWithoutForm) && $nameWithoutForm !== $reqName) {
                $strippedQuery = NormalizedProduct::query();
                if (! empty($reqStrength)) {
                    $strippedQuery->where('parsed_name', $nameWithoutForm)
                        ->where('parsed_strength', $reqStrength);
                } else {
                    $strippedQuery->where('parsed_name', $nameWithoutForm);
                }
                $exact = $strippedQuery->first();
            }
        }

        if ($exact) {
            return [
                'status' => 'exact',
                'product' => $exact,
                'confidence' => 100.0,
                'matched_name' => trim("{$exact->parsed_name} {$exact->parsed_strength}"),
                'suggestions' => [],
                'reason' => null,
            ];
        }

        // 3. Fuzzy & Token-Based Matching
        $reqTokens = $this->extractTokens($reqName);
        if (empty($reqTokens)) {
            return [
                'status' => 'unmatched',
                'product' => null,
                'confidence' => 0.0,
                'total_candidates' => 0,
                'matched_name' => null,
                'suggestions' => [],
                'reason' => 'Nama obat tidak valid atau terlalu pendek',
            ];
        }

        $candidates = $this->findCandidatePool($reqTokens, $reqStrength);
        if ($candidates->isEmpty()) {
            if ($useAi && $this->aiService && $this->aiService->isAvailable()) {
                $eval = $this->aiService->evaluateUnmatchedProduct($rawName);
                if ($eval && ($eval['confidence'] ?? 0) >= 80.0) {
                    $status = $eval['decision'] === 'not_stocked' ? 'not_stocked' : 'unlisted';
                    ProductAlias::firstOrCreate(
                        ['alias_normalized' => $canonical],
                        [
                            'alias_raw' => $cleanRaw,
                            'status' => $status,
                            'normalized_product_id' => null,
                        ]
                    );

                    return [
                        'status' => $status,
                        'product' => null,
                        'confidence' => $eval['confidence'],
                        'total_candidates' => 0,
                        'matched_name' => $status === 'not_stocked' ? 'Bukan Stok PBF (Belanja Mandiri)' : 'Belum Ada di PBF Rekanan',
                        'suggestions' => [],
                        'reason' => 'Identifikasi AI: '.($eval['reason'] ?? ''),
                    ];
                }
            }

            return [
                'status' => 'unmatched',
                'product' => null,
                'confidence' => 0.0,
                'total_candidates' => 0,
                'matched_name' => null,
                'suggestions' => [],
                'reason' => 'Tidak ditemukan produk di pricelist supplier manapun',
            ];
        }

        $scoredCandidates = [];
        foreach ($candidates as $candidate) {
            $score = $this->calculateSimilarityScore($reqName, $reqTokens, $reqStrength, $reqForm, $candidate);
            // Ambang batas ketat: hanya simpan jika skor >= 70% dan brand terverifikasi cocok
            if ($score >= 70.0) {
                $scoredCandidates[] = [
                    'product_id' => $candidate->id,
                    'product' => $candidate,
                    'name' => trim("{$candidate->parsed_name} {$candidate->parsed_strength}"),
                    'confidence' => round($score, 1),
                ];
            }
        }

        if (empty($scoredCandidates)) {
            // Jika fuzzy score tidak mencapai 70% (misal singkatan/nama komersial), tanyakan ke AI dengan 4 pertimbangan ketat
            if ($useAi && $this->aiService && $this->aiService->isAvailable() && $candidates->isNotEmpty()) {
                $aiResult = $this->aiService->matchDrug($rawName, $candidates->take(8));
                if ($aiResult && ! empty($aiResult['matched_id']) && ($aiResult['confidence'] ?? 0) >= 80.0) {
                    $aiProduct = $aiResult['product'] ?? NormalizedProduct::find($aiResult['matched_id']);
                    if ($aiProduct) {
                        ProductAlias::firstOrCreate(
                            ['alias_normalized' => $canonical],
                            [
                                'alias_raw' => $cleanRaw,
                                'normalized_product_id' => $aiProduct->id,
                                'target_canonical' => $aiProduct->normalized_name,
                            ]
                        );

                        return [
                            'status' => 'ai_matched',
                            'product' => $aiProduct,
                            'confidence' => $aiResult['confidence'],
                            'total_candidates' => $candidates->count(),
                            'matched_name' => trim("{$aiProduct->parsed_name} {$aiProduct->parsed_strength}"),
                            'suggestions' => [],
                            'reason' => 'Cocok via AI: '.($aiResult['reason'] ?? ''),
                        ];
                    }
                } elseif ($aiResult && ($aiResult['decision'] ?? '') === 'not_stocked' && ($aiResult['confidence'] ?? 0) >= 80.0) {
                    ProductAlias::firstOrCreate(
                        ['alias_normalized' => $canonical],
                        [
                            'alias_raw' => $cleanRaw,
                            'status' => 'not_stocked',
                            'normalized_product_id' => null,
                        ]
                    );

                    return [
                        'status' => 'not_stocked',
                        'product' => null,
                        'confidence' => $aiResult['confidence'],
                        'total_candidates' => 0,
                        'matched_name' => 'Bukan Stok PBF (Belanja Mandiri)',
                        'suggestions' => [],
                        'reason' => 'AI: '.($aiResult['reason'] ?? ''),
                    ];
                }
            }

            return [
                'status' => 'unmatched',
                'product' => null,
                'confidence' => 0.0,
                'total_candidates' => 0,
                'matched_name' => null,
                'suggestions' => [],
                'reason' => 'Tidak ditemukan produk yang cocok di pricelist supplier',
            ];
        }

        usort($scoredCandidates, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        $best = $scoredCandidates[0];
        $runnerUp = $scoredCandidates[1] ?? null;
        $totalCandidates = count($scoredCandidates);

        // Kriteria Auto-Match: Skor >= 85% dan tidak terlalu ambigu (gap >= 5% atau kandidat tunggal dominan)
        $isDominant = $runnerUp === null
            || ($best['confidence'] - $runnerUp['confidence'] >= 5.0)
            || ($best['confidence'] >= 90.0);

        if ($best['confidence'] >= 85.0 && $isDominant) {
            ProductAlias::firstOrCreate(
                ['alias_normalized' => $canonical],
                [
                    'alias_raw' => $cleanRaw,
                    'normalized_product_id' => $best['product']->id,
                    'target_canonical' => $best['product']->normalized_name,
                ]
            );

            return [
                'status' => 'auto_matched',
                'product' => $best['product'],
                'confidence' => $best['confidence'],
                'total_candidates' => $totalCandidates,
                'matched_name' => $best['name'],
                'suggestions' => array_slice($scoredCandidates, 0, 5),
                'reason' => null,
            ];
        }

        // Konsultasikan ke AI untuk memutuskan varian terbaik jika ambigu
        if ($useAi && $this->aiService && $this->aiService->isAvailable()) {
            $aiCandidatePool = collect($scoredCandidates)->pluck('product')->filter();
            if ($aiCandidatePool->isEmpty()) {
                $aiCandidatePool = $candidates;
            }

            $aiResult = $this->aiService->matchDrug($rawName, $aiCandidatePool->take(6));
            if ($aiResult && ! empty($aiResult['matched_id']) && ($aiResult['confidence'] ?? 0) >= 80.0) {
                $aiProduct = $aiResult['product'] ?? NormalizedProduct::find($aiResult['matched_id']);
                if ($aiProduct) {
                    ProductAlias::firstOrCreate(
                        ['alias_normalized' => $canonical],
                        [
                            'alias_raw' => $cleanRaw,
                            'normalized_product_id' => $aiProduct->id,
                            'target_canonical' => $aiProduct->normalized_name,
                        ]
                    );

                    return [
                        'status' => 'ai_matched',
                        'product' => $aiProduct,
                        'confidence' => $aiResult['confidence'],
                        'total_candidates' => $totalCandidates,
                        'matched_name' => trim("{$aiProduct->parsed_name} {$aiProduct->parsed_strength}"),
                        'suggestions' => array_slice($scoredCandidates, 0, 5),
                        'reason' => 'Cocok via AI: '.($aiResult['reason'] ?? ''),
                    ];
                }
            } elseif ($aiResult && ($aiResult['decision'] ?? '') === 'not_stocked' && ($aiResult['confidence'] ?? 0) >= 80.0) {
                ProductAlias::firstOrCreate(
                    ['alias_normalized' => $canonical],
                    [
                        'alias_raw' => $cleanRaw,
                        'status' => 'not_stocked',
                        'normalized_product_id' => null,
                    ]
                );

                return [
                    'status' => 'not_stocked',
                    'product' => null,
                    'confidence' => $aiResult['confidence'],
                    'total_candidates' => 0,
                    'matched_name' => 'Bukan Stok PBF (Belanja Mandiri)',
                    'suggestions' => [],
                    'reason' => 'AI: '.($aiResult['reason'] ?? ''),
                ];
            }
        }

        $reason = $totalCandidates > 1
            ? "Terdapat {$totalCandidates} varian produk serupa di pricelist PBF"
            : 'Tingkat kemiripan ('.round($best['confidence']).'%) butuh konfirmasi';

        return [
            'status' => 'ambiguous',
            'product' => null,
            'confidence' => $best['confidence'],
            'total_candidates' => $totalCandidates,
            'matched_name' => null,
            'suggestions' => array_slice($scoredCandidates, 0, 5),
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<string>  $reqTokens
     */
    protected function findCandidatePool(array $reqTokens, ?string $reqStrength): Collection
    {
        if (empty($reqTokens)) {
            return collect();
        }

        $query = NormalizedProduct::query();
        if (! empty($reqStrength)) {
            $query->where(function ($q) use ($reqStrength) {
                $q->where('parsed_strength', $reqStrength)
                    ->orWhereNull('parsed_strength');
            });
        }

        $candidates = collect();

        // 1. Ekstrak token nama obat yang signifikan
        $sigTokens = array_values(array_filter($reqTokens, fn ($t) => $this->isSignificantToken($t)));
        if (empty($sigTokens)) {
            $sigTokens = $reqTokens;
        }

        // 2. Pencarian Substring Langsung untuk token utama (misal: '%ALERZIN%' cocok ke HUFALERZINE)
        $primary = $sigTokens[0] ?? '';
        if (mb_strlen($primary) >= 3) {
            $bySubstring = (clone $query)
                ->where(function ($q) use ($primary) {
                    $q->where('parsed_name', 'like', '%'.$primary.'%')
                        ->orWhere('normalized_name', 'like', '%'.$primary.'%');
                })
                ->limit(50)
                ->get();
            $candidates = $candidates->merge($bySubstring);
        }

        // 3. Pecah kata gabungan (contoh: TERMOREXPLUS -> TERMOREX + PLUS)
        if (mb_strlen($primary) >= 7) {
            $stems = preg_split('/(PLUS|FORTE|EXTRA|DROP|SYR|TAB|KAP)/i', $primary, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($stems)) {
                foreach ($stems as $stem) {
                    if (mb_strlen($stem) >= 4 && $stem !== $primary) {
                        $stemMatches = (clone $query)
                            ->where('parsed_name', 'like', '%'.$stem.'%')
                            ->limit(30)
                            ->get();
                        $candidates = $candidates->merge($stemMatches);
                    }
                }
            }
        }

        // 4. Ekspansi singkatan umum farmasi Indonesia jika token cocok
        $abbreviations = [
            'PCT' => 'PARACETAMOL',
            'PARACET' => 'PARACETAMOL',
            'CTM' => 'CHLORPHENIRAMINE',
            'GG' => 'GUAIFENESIN',
            'AMOX' => 'AMOXICILLIN',
            'ASMEF' => 'ASAM MEFENAMAT',
            'METRO' => 'METRONIDAZOLE',
            'CIPRO' => 'CIPROFLOXACIN',
            'AMLO' => 'AMLODIPINE',
            'SIMVA' => 'SIMVASTATIN',
            'OMEP' => 'OMEPRAZOLE',
            'LANSOP' => 'LANSOPRAZOLE',
            'DEXA' => 'DEXAMETHASONE',
            'SALBU' => 'SALBUTAMOL',
            'OBH' => 'OBH',
        ];

        foreach ($reqTokens as $tok) {
            if (isset($abbreviations[$tok])) {
                $expanded = $abbreviations[$tok];
                $expandedMatches = (clone $query)->where('parsed_name', 'like', '%'.$expanded.'%')->limit(50)->get();
                $candidates = $candidates->merge($expandedMatches);
            }
        }

        // 5. Pencarian token signifikan lainnya jika kandidat masih sedikit
        if ($candidates->count() < 10 && count($sigTokens) > 1) {
            $otherTokens = array_slice($sigTokens, 1);
            $tokenQuery = clone $query;
            $tokenQuery->where(function ($q) use ($otherTokens) {
                foreach ($otherTokens as $tok) {
                    $q->orWhere('parsed_name', 'like', '%'.$tok.'%');
                }
            });
            $res = $tokenQuery->limit(50)->get();
            $candidates = $candidates->merge($res);
        }

        // 6. Fallback prefix match jika masih kosong
        if ($candidates->isEmpty() && mb_strlen($primary) >= 4) {
            $prefix = mb_substr($primary, 0, 4);
            $prefixMatches = (clone $query)->where('parsed_name', 'like', $prefix.'%')->limit(30)->get();
            $candidates = $candidates->merge($prefixMatches);
        }

        return $candidates->unique('id')->values();
    }

    /**
     * @param  array<string>  $reqTokens
     */
    protected function calculateSimilarityScore(
        string $reqName,
        array $reqTokens,
        ?string $reqStrength,
        ?string $reqForm,
        NormalizedProduct $candidate
    ): float {
        $candName = (string) $candidate->parsed_name;
        $candStrength = (string) $candidate->parsed_strength;
        $candForm = (string) $candidate->parsed_form;

        // 1. Guard Dosis: Jika kedua pihak mencantumkan dosis, wajib identik
        if (! empty($reqStrength) && ! empty($candStrength)) {
            if ($reqStrength !== $candStrength) {
                return 0.0;
            }
        }

        // 2. Guard Bentuk Sediaan (Tablet vs Salep Mata vs Sirup vs Injeksi)
        $reqCat = $this->getDosageCategory($reqName, $reqForm);
        $candCat = $this->getDosageCategory($candName, $candForm);
        if ($reqCat !== null && $candCat !== null && $reqCat !== $candCat) {
            return 0.0;
        }

        // 3. Ekstrak token kandidat
        $candTokens = $this->extractTokens("{$candName} {$candStrength} {$candForm}");
        if (empty($candTokens)) {
            return 0.0;
        }

        // 4. Guard Brand / Nama Utama: Wajib ada token nama obat signifikan yang cocok (identik atau typo maks 1 huruf)
        // Mencegah ALLERGEN salah dipasangkan ke ALLETROL, atau ALERZIN ke ALERTEN
        if (! $this->hasMatchingSignificantToken($reqTokens, $candTokens)) {
            return 0.0;
        }

        // 5. Token Jaccard & Containment Score
        $intersection = array_intersect($reqTokens, $candTokens);
        $intersectionCount = count($intersection);
        $unionCount = count(array_unique(array_merge($reqTokens, $candTokens)));

        $jaccard = $unionCount > 0 ? ($intersectionCount / $unionCount) : 0.0;
        $containment = count($reqTokens) > 0 ? ($intersectionCount / count($reqTokens)) : 0.0;

        // Jika semua kata permintaan ada pada kandidat (misal: ARKAVIT KAPLET ada di ARKAVIT KAPL. BALATIF)
        if ($containment >= 1.0 && $intersectionCount > 0) {
            $tokenScore = 80.0 + (15.0 * $jaccard);
        } else {
            $tokenScore = (0.7 * $containment + 0.3 * $jaccard) * 100.0;
        }

        // 6. Token-Sorted Similarity & Typo matching
        $sortedReq = $reqTokens;
        sort($sortedReq);
        $sortedCand = $candTokens;
        sort($sortedCand);

        similar_text(implode(' ', $sortedReq), implode(' ', $sortedCand), $sortedSim);
        similar_text($reqName, $candName, $rawSim);

        $finalScore = max($tokenScore, (float) $sortedSim, (float) $rawSim);

        // Boost jika nama brand utama cocok persis (misal ALERHIS == ALERHIS, ALERMAX == ALERMAX)
        $reqSigTokens = array_values(array_filter($reqTokens, fn ($t) => $this->isSignificantToken($t)));
        if (! empty($reqSigTokens) && in_array($reqSigTokens[0], $candTokens, true)) {
            $finalScore = max($finalScore, 75.0);
        }

        // Boost jika dosis cocok persis
        if (! empty($reqStrength) && $reqStrength === $candStrength) {
            $finalScore = min(100.0, $finalScore + 5.0);
        }

        return $finalScore;
    }

    /**
     * Memeriksa apakah dua produk master NormalizedProduct adalah obat yang setara
     * (Zat aktif/brand sama, dosis sama, rute sediaan sama, dan pabrikan sama jika tercantum)
     */
    public function areEquivalent(NormalizedProduct $p1, NormalizedProduct $p2): bool
    {
        // 1. Dosis wajib identik
        $str1 = $p1->parsed_strength ? strtoupper(trim($p1->parsed_strength)) : null;
        $str2 = $p2->parsed_strength ? strtoupper(trim($p2->parsed_strength)) : null;
        if ($str1 !== $str2) {
            return false;
        }

        // 2. Kategori bentuk sediaan wajib sama
        $cat1 = $this->getDosageCategory((string) $p1->parsed_name, $p1->parsed_form);
        $cat2 = $this->getDosageCategory((string) $p2->parsed_name, $p2->parsed_form);
        if ($cat1 !== null && $cat2 !== null && $cat1 !== $cat2) {
            return false;
        }

        // 3. Ekstrak token signifikan
        $toks1 = array_values(array_filter(
            $this->extractTokens((string) $p1->parsed_name),
            fn ($t) => $this->isSignificantToken($t)
        ));
        $toks2 = array_values(array_filter(
            $this->extractTokens((string) $p2->parsed_name),
            fn ($t) => $this->isSignificantToken($t)
        ));

        if (empty($toks1) || empty($toks2)) {
            return false;
        }

        // Brand / Zat aktif utama wajib cocok
        $brand1 = $toks1[0];
        $brand2 = $toks2[0];
        $brandMatch = ($brand1 === $brand2)
            || (mb_strlen($brand1) >= 5 && mb_strlen($brand2) >= 5 && levenshtein($brand1, $brand2) <= 1);

        if (! $brandMatch) {
            return false;
        }

        // Jika salah satu produk mencantumkan pabrikan/kualifikasi spesifik (misal NOVA, BALATIF, DEXA, IFARS, SELES),
        // maka produk lain wajib mengandung kualifikasi yang cocok
        $qualifiers1 = array_slice($toks1, 1);
        $qualifiers2 = array_slice($toks2, 1);

        if (! empty($qualifiers1) && ! empty($qualifiers2)) {
            $hasCommonQualifier = false;
            foreach ($qualifiers1 as $q1) {
                foreach ($qualifiers2 as $q2) {
                    if ($q1 === $q2 || str_starts_with($q2, $q1) || str_starts_with($q1, $q2)) {
                        $hasCommonQualifier = true;
                        break 2;
                    }
                }
            }

            if (! $hasCommonQualifier) {
                return false;
            }
        }

        return true;
    }

    /**
     * Guard Kategori Bentuk Sediaan: Mencegah tablet tertukar dengan salep mata, sirup, dll.
     */
    protected function getDosageCategory(string $name, ?string $form): ?string
    {
        $combined = mb_strtoupper($name.' '.($form ?? ''));

        if (preg_match('/\b(salep\s*mata|tetes\s*mata|tetes\s*telinga|gtt|eye\s*drop|eye\s*oint|ear\s*drop)\b/i', $combined)) {
            return 'OPHTHALMIC_OTIC';
        }
        if (preg_match('/\b(salep|cream|krim|cr|oint|ointment|gel|lotion|pasta)\b/i', $combined)) {
            return 'TOPICAL';
        }
        if (preg_match('/\b(syr|sirup|susp|suspensi|emulsi|elixir|drop|drops)\b/i', $combined)) {
            return 'LIQUID_ORAL';
        }
        if (preg_match('/\b(tab|tablet|kap|kaplet|kaps|kapsul|pil)\b/i', $combined)) {
            return 'SOLID_ORAL';
        }
        if (preg_match('/\b(inj|injeksi|infus|vial|amp|ampul)\b/i', $combined)) {
            return 'INJECTABLE';
        }

        return null;
    }

    /**
     * Memeriksa apakah token adalah kata nama obat / zat aktif signifikan (bukan bentuk sediaan / noise)
     */
    protected function isSignificantToken(string $token): bool
    {
        $nonSignificant = [
            'TAB', 'KAP', 'SYR', 'INJ', 'DROP', 'OINT', 'CR', 'SUSP',
            'SALEP', 'CREAM', 'KRIM', 'TABLET', 'KAPLET', 'KAPSUL', 'SIRUP',
            'OBAT', 'MINYAK', 'BALSEM', 'CAP', 'CAPS', 'GEL', 'LOTION', 'PASTA',
            'MATA', 'TELINGA', 'KULIT', 'GEN', 'GENERIC', 'GENERIK',
            'FORTE', 'PLUS', 'EXTRA', 'SERUM', 'GR', 'GM', 'ML', 'MG', 'MCG', 'IU',
            'NO', 'NEW', 'UK', 'BESAR', 'KECIL', 'D', 'R',
        ];

        return mb_strlen($token) >= 3 && ! in_array($token, $nonSignificant, true);
    }

    /**
     * Guard Brand / Nama Utama: Wajib memiliki kesamaan kata nama obat utama.
     * Mencegah ALLERGEN salah dicocokkan ke ALLETROL, atau ALERZIN ke ALERTEN.
     *
     * @param  array<string>  $reqTokens
     * @param  array<string>  $candTokens
     */
    protected function hasMatchingSignificantToken(array $reqTokens, array $candTokens): bool
    {
        $reqSigTokens = array_values(array_filter($reqTokens, fn ($t) => $this->isSignificantToken($t)));
        if (empty($reqSigTokens)) {
            return true;
        }

        $candSigTokens = array_values(array_filter($candTokens, fn ($t) => $this->isSignificantToken($t)));
        if (empty($candSigTokens)) {
            return false;
        }

        $primaryReq = $reqSigTokens[0];

        foreach ($candSigTokens as $candTok) {
            // Cocok persis
            if ($primaryReq === $candTok) {
                return true;
            }

            // Substring containment (misal ALERZIN di HUFALERZINE, atau TERMOREX di TERMOREXPLUS)
            if (mb_strlen($primaryReq) >= 5 && mb_strlen($candTok) >= 5) {
                if (str_contains($candTok, $primaryReq) || str_contains($primaryReq, $candTok)) {
                    return true;
                }
            }

            // Typo minor (jarak edit <= 1 untuk kata dengan panjang >= 5 huruf)
            // Contoh: AMOXICILIN vs AMOXICILLIN (dist 1, len 10)
            if (mb_strlen($primaryReq) >= 5 && mb_strlen($candTok) >= 5) {
                if (levenshtein($primaryReq, $candTok) <= 1) {
                    return true;
                }
            }
        }

        // Jika request punya lebih dari 1 token signifikan (misal: "ASAM MEFENAMAT"),
        // cek apakah ada token signifikan lain yang cocok
        if (count($reqSigTokens) > 1) {
            foreach ($reqSigTokens as $reqTok) {
                foreach ($candSigTokens as $candTok) {
                    if ($reqTok === $candTok) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    protected function extractTokens(string $name): array
    {
        // Bersihkan tanda baca, tanda petik, dan simbol pembatas
        $cleaned = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);
        $words = preg_split('/\s+/', mb_strtoupper(trim($cleaned)));

        $formSynonyms = [
            'KAPLET' => 'KAP', 'KAPL' => 'KAP', 'KAPSUL' => 'KAP', 'KAPS' => 'KAP',
            'TABLET' => 'TAB', 'TABL' => 'TAB',
            'SIRUP' => 'SYR', 'SYRUP' => 'SYR', 'SUSPENSI' => 'SYR', 'SUSP' => 'SYR',
            'CREAM' => 'CR', 'KRIM' => 'CR', 'SALEP' => 'OINT',
            'INJEKSI' => 'INJ', 'TETES' => 'DROP', 'DROPS' => 'DROP',
            'GRAM' => 'GR', 'GM' => 'GR',
        ];

        $tokens = [];
        foreach ($words as $w) {
            $w = trim($w);
            if (mb_strlen($w) > 1) {
                $tokens[] = $formSynonyms[$w] ?? $w;
            }
        }

        return array_values(array_unique($tokens));
    }
}

<?php

namespace App\Services;

use App\Models\NormalizedProduct;
use App\Models\ProductAlias;
use Illuminate\Support\Collection;

class DrugMatchingService
{
    public function __construct(
        protected ProductNormalizationService $normalizer
    ) {}

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
    public function match(string $rawName): array
    {
        $cleanRaw = mb_strtolower(trim($rawName));
        $parsed = $this->normalizer->parseDrugName($rawName, '');
        $canonical = $parsed['canonical'];
        $reqName = $parsed['name'];
        $reqStrength = $parsed['strength'];
        $reqForm = $parsed['form'];

        // 1. Cek Alias Terverifikasi (Kamus Manual / Tersimpan Sebelumnya)
        $alias = ProductAlias::where('alias_normalized', $canonical)
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

            if ($alias->normalizedProduct) {
                $product = $alias->normalizedProduct;

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
                'matched_name' => null,
                'suggestions' => [],
                'reason' => 'Nama obat tidak valid atau terlalu pendek',
            ];
        }

        $candidates = $this->findCandidatePool($reqTokens, $reqStrength);
        if ($candidates->isEmpty()) {
            return [
                'status' => 'unmatched',
                'product' => null,
                'confidence' => 0.0,
                'matched_name' => null,
                'suggestions' => [],
                'reason' => 'Tidak ditemukan produk di pricelist supplier',
            ];
        }

        $scoredCandidates = [];
        foreach ($candidates as $candidate) {
            $score = $this->calculateSimilarityScore($reqName, $reqTokens, $reqStrength, $reqForm, $candidate);
            // Ambang batas saran dinaikkan ke minimal 70% agar produk non-obat tidak dipaksakan cocok
            if ($score >= 70.0) {
                $scoredCandidates[] = [
                    'product' => $candidate,
                    'name' => trim("{$candidate->parsed_name} {$candidate->parsed_strength}"),
                    'confidence' => round($score, 1),
                ];
            }
        }

        if (empty($scoredCandidates)) {
            return [
                'status' => 'unmatched',
                'product' => null,
                'confidence' => 0.0,
                'matched_name' => null,
                'suggestions' => [],
                'reason' => 'Tidak ada produk dengan kemiripan yang cukup (di bawah 70%)',
            ];
        }

        usort($scoredCandidates, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        $best = $scoredCandidates[0];
        $runnerUp = $scoredCandidates[1] ?? null;

        // Kriteria Auto-Match: Skor >= 85% dan bukan kasus ambigu yang terlalu berdekatan
        $isDominant = $runnerUp === null
            || ($best['confidence'] - $runnerUp['confidence'] >= 8.0)
            || ($best['confidence'] >= 94.0);

        if ($best['confidence'] >= 85.0 && $isDominant) {
            ProductAlias::firstOrCreate(
                ['alias_normalized' => $canonical],
                [
                    'alias_raw' => $cleanRaw,
                    'normalized_product_id' => $best['product']->id,
                ]
            );

            return [
                'status' => 'auto_matched',
                'product' => $best['product'],
                'confidence' => $best['confidence'],
                'matched_name' => $best['name'],
                'suggestions' => array_slice($scoredCandidates, 0, 3),
                'reason' => null,
            ];
        }

        return [
            'status' => 'ambiguous',
            'product' => null,
            'confidence' => $best['confidence'],
            'matched_name' => null,
            'suggestions' => array_slice($scoredCandidates, 0, 3),
            'reason' => ($runnerUp && ($best['confidence'] - $runnerUp['confidence'] < 8.0))
                ? 'Terdapat beberapa varian produk serupa'
                : 'Tingkat kemiripan ('.round($best['confidence']).'%) butuh konfirmasi',
        ];
    }

    /**
     * @param  array<string>  $reqTokens
     */
    protected function findCandidatePool(array $reqTokens, ?string $reqStrength): Collection
    {
        $firstToken = $reqTokens[0] ?? '';
        $prefix = mb_substr($firstToken, 0, 4);

        $query = NormalizedProduct::query();
        if (! empty($reqStrength)) {
            $query->where(function ($q) use ($reqStrength) {
                $q->where('parsed_strength', $reqStrength)
                    ->orWhereNull('parsed_strength');
            });
        }

        if (mb_strlen($prefix) >= 3) {
            $narrowed = (clone $query)->where('parsed_name', 'like', $prefix.'%')->get();
            if ($narrowed->isNotEmpty()) {
                return $narrowed;
            }
        }

        if (mb_strlen($firstToken) >= 3) {
            $byToken = (clone $query)->where('parsed_name', 'like', '%'.$firstToken.'%')->get();
            if ($byToken->isNotEmpty()) {
                return $byToken;
            }
        }

        return $query->limit(1000)->get();
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

        // 2. Guard Sediaan Cair vs Padat (mencegah sirup tertukar dengan tablet)
        $isReqLiquid = in_array($reqForm, ['SYR', 'DROP', 'SUSP'], true) || preg_match('/\b(syr|drop|susp|sirup)\b/i', $reqName);
        $isCandLiquid = in_array($candForm, ['SYR', 'DROP', 'SUSP'], true) || preg_match('/\b(syr|drop|susp|sirup)\b/i', $candName);

        $isReqSolid = in_array($reqForm, ['TAB', 'KAP'], true) || preg_match('/\b(tab|kap|tablet|kaplet)\b/i', $reqName);
        $isCandSolid = in_array($candForm, ['TAB', 'KAP'], true) || preg_match('/\b(tab|kap|tablet|kaplet)\b/i', $candName);

        if (($isReqLiquid && $isCandSolid) || ($isReqSolid && $isCandLiquid)) {
            return 0.0;
        }

        // 3. Token Jaccard & Containment Score
        $candTokens = $this->extractTokens($candName);
        if (empty($candTokens)) {
            return 0.0;
        }

        $intersection = array_intersect($reqTokens, $candTokens);
        $intersectionCount = count($intersection);
        $unionCount = count(array_unique(array_merge($reqTokens, $candTokens)));

        $jaccard = $unionCount > 0 ? ($intersectionCount / $unionCount) : 0.0;
        $containment = count($reqTokens) > 0 ? ($intersectionCount / count($reqTokens)) : 0.0;

        $tokenScore = (0.6 * $containment + 0.4 * $jaccard) * 100.0;

        // 4. Token-Sorted Similarity (Mengatasi urutan kata terbalik)
        $sortedReq = $reqTokens;
        sort($sortedReq);
        $sortedCand = $candTokens;
        sort($sortedCand);

        similar_text(implode(' ', $sortedReq), implode(' ', $sortedCand), $sortedSim);
        similar_text($reqName, $candName, $rawSim);

        $finalScore = max($tokenScore, (float) $sortedSim, (float) $rawSim);

        // Boost jika dosis cocok persis
        if (! empty($reqStrength) && $reqStrength === $candStrength) {
            $finalScore = min(100.0, $finalScore + 5.0);
        }

        return $finalScore;
    }

    /**
     * @return array<string>
     */
    protected function extractTokens(string $name): array
    {
        $words = preg_split('/\s+/', mb_strtoupper(trim($name)));
        $tokens = [];
        foreach ($words as $w) {
            $w = trim($w);
            if (mb_strlen($w) > 1) {
                $tokens[] = $w;
            }
        }

        return array_values(array_unique($tokens));
    }
}

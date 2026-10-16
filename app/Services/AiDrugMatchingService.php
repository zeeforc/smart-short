<?php

namespace App\Services;

use App\Models\NormalizedProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiDrugMatchingService
{
    protected ?string $apiKey;

    protected string $model;

    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key');
        $this->model = config('services.groq.model', 'qwen/qwen3.8-27b');
        $this->timeout = (int) config('services.groq.timeout', 15);
    }

    // check API key
    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Mencocokkan 1 nama obat defecta dengan daftar kandidat produk dari master pricelist
     * menggunakan 4 pertimbangan ketat farmasi.
     *
     * @param  string  $rawName  Nama obat mentah dari defecta (contoh: "AMOX 500", "PCT SYR")
     * @param  Collection<int, NormalizedProduct>|array<int, mixed>  $candidates  Daftar kandidat produk dari database
     * @return array{
     *     decision: string,
     *     matched_id: ?int,
     *     confidence: float,
     *     reason: string,
     *     product: ?NormalizedProduct
     * }|null
     */
    public function matchDrug(string $rawName, Collection|array $candidates): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $candidateList = $this->formatCandidates($candidates);
        if (empty($candidateList)) {
            return $this->evaluateUnmatchedProduct($rawName);
        }

        $prompt = $this->buildSingleMatchPrompt($rawName, $candidateList);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Kamu adalah asisten apoteker senior dan ahli inventaris obat apotek di Indonesia. Tugasmu adalah mencocokkan nama obat dari defecta pesanan dengan produk katalog distributor PBF dengan 4 pertimbangan ketat: 1) Sediaan wajib sejenis (Tablet vs Sirup vs Salep vs Injeksi dilarang silang), 2) Dosis & volume harus kompatibel, 3) Memahami singkatan dagang dan awalan/akhiran pabrik farmasi Indonesia, 4) Jangan memaksakan mencocokkan jika produk memang bukan stok PBF/tidak ada. Kembalikan HANYA format JSON valid.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1,
                'max_tokens' => 400,
            ]);

            if (! $response->successful()) {
                Log::warning('Groq AI Matching request failed: '.$response->body());

                return null;
            }

            $json = $response->json();
            $content = $json['choices'][0]['message']['content'] ?? null;

            if (! $content) {
                return null;
            }

            $parsed = json_decode($content, true);
            if (! is_array($parsed)) {
                return null;
            }

            $decision = (string) ($parsed['decision'] ?? 'ambiguous');
            $matchedId = isset($parsed['matched_id']) && is_numeric($parsed['matched_id'])
                ? (int) $parsed['matched_id']
                : null;
            $confidence = isset($parsed['confidence']) ? (float) $parsed['confidence'] : 0.0;
            $reason = (string) ($parsed['reason'] ?? '');

            $matchedProduct = null;
            if ($matchedId) {
                if ($candidates instanceof Collection) {
                    $matchedProduct = $candidates->firstWhere('id', $matchedId);
                } else {
                    foreach ($candidates as $c) {
                        $cId = is_array($c) ? ($c['id'] ?? null) : ($c->id ?? null);
                        if ((int) $cId === $matchedId) {
                            $matchedProduct = $c instanceof NormalizedProduct ? $c : NormalizedProduct::find($matchedId);
                            break;
                        }
                    }
                }

                if (! $matchedProduct) {
                    $matchedProduct = NormalizedProduct::find($matchedId);
                }
            }

            if ($matchedProduct) {
                $decision = 'matched';
            } elseif ($decision === 'matched') {
                $decision = 'ambiguous';
            }

            return [
                'decision' => $decision,
                'matched_id' => $matchedProduct ? $matchedProduct->id : null,
                'confidence' => $confidence,
                'reason' => $reason,
                'product' => $matchedProduct,
            ];
        } catch (\Throwable $e) {
            Log::error('AiDrugMatchingService error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Evaluasi obat yang tidak memiliki kandidat sama sekali di database.
     * AI mendeteksi apakah obat ini termasuk alat kesehatan, perlengkapan medis, atau produk non-PBF.
     *
     * @return array{
     *     decision: string,
     *     matched_id: ?int,
     *     confidence: float,
     *     reason: string,
     *     product: null
     * }|null
     */
    public function evaluateUnmatchedProduct(string $rawName): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $prompt = <<<PROMPT
Analisis nama barang berikut yang berasal dari pesanan apotek: "{$rawName}"
Barang ini tidak ditemukan kandidatnya sama sekali di katalog pricelist distributor obat PBF.

Tentukan apakah barang ini:
1. "not_stocked" (Alat kesehatan, perlengkapan medis seperti celana khitan/kasa/spuit/arm sling/handscoon, produk consumer goods/kebersihan seperti pantyliner/pembalut, atau suplemen non-PBF yang biasa dibeli mandiri).
2. "unlisted" (Obat farmasi resmi tetapi distributor rekanan apotek saat ini memang belum menyediakan stoknya).

Kembalikan HANYA format JSON valid berikut:
{
  "decision": "not_stocked" atau "unlisted",
  "confidence": <angka_80_sampai_100>,
  "reason": "<penjelasan singkat kategori barang>"
}
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Kamu adalah asisten apoteker ahli pengadaan apotek. Kategorikan produk yang tidak ada di pricelist distributor PBF menjadi not_stocked (alkes/non-obat) atau unlisted (obat belum ada rekanan). Kembalikan HANYA format JSON valid.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1,
                'max_tokens' => 200,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();
            $content = $json['choices'][0]['message']['content'] ?? null;
            if (! $content) {
                return null;
            }

            $parsed = json_decode($content, true);
            if (! is_array($parsed)) {
                return null;
            }

            $decision = ($parsed['decision'] ?? '') === 'not_stocked' ? 'not_stocked' : 'unlisted';
            $confidence = isset($parsed['confidence']) ? (float) $parsed['confidence'] : 90.0;
            $reason = (string) ($parsed['reason'] ?? 'Produk terdeteksi bukan stok PBF');

            return [
                'decision' => $decision,
                'matched_id' => null,
                'confidence' => $confidence,
                'reason' => $reason,
                'product' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('AiDrugMatchingService evaluateUnmatched error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Format daftar kandidat menjadi teks ringkas untuk prompt.
     *
     * @return array<int, array{id: int, text: string}>
     */
    protected function formatCandidates(Collection|array $candidates): array
    {
        $list = [];
        $items = $candidates instanceof Collection ? $candidates : collect($candidates);

        foreach ($items->take(10) as $item) {
            if ($item instanceof NormalizedProduct) {
                $id = $item->id;
                $name = trim("{$item->parsed_name} {$item->parsed_strength} {$item->parsed_form}");
                $canonical = $item->normalized_name;
            } elseif (is_array($item)) {
                $id = $item['id'] ?? ($item['product']['id'] ?? null);
                $name = $item['name'] ?? ($item['product']['parsed_name'] ?? '');
                $canonical = $item['canonical'] ?? '';
            } else {
                continue;
            }

            if ($id) {
                $list[] = [
                    'id' => (int) $id,
                    'text' => "ID: {$id} | Nama: {$name} (Katalog: {$canonical})",
                ];
            }
        }

        return $list;
    }

    /**
     * Membangun prompt kecocokan obat dengan 4 pertimbangan ketat.
     *
     * @param  array<int, array{id: int, text: string}>  $candidateList
     */
    protected function buildSingleMatchPrompt(string $rawName, array $candidateList): string
    {
        $candidatesText = '';
        foreach ($candidateList as $c) {
            $candidatesText .= "- {$c['text']}\n";
        }

        return <<<PROMPT
Cocokkan nama obat dari defecta apotek berikut dengan SALAH SATU kandidat dari katalog distributor PBF.

Nama Obat Defecta: "{$rawName}"

Daftar Kandidat PBF:
{$candidatesText}

4 PERTIMBANGAN KETAT (WAJIB DIPATUHI):
1. BENTUK SEDIAAN (DOSAGE FORM) WAJIB KOMPATIBEL:
   - Tablet/Kaplet/Kapsul HANYA boleh cocok dengan sediaan padat oral (Tab/Kap/Kaps).
   - Sirup/Suspensi/Dry Syrup HANYA boleh cocok dengan sediaan cair oral (Syr/Susp/Drop oral).
   - Salep/Krim/Gel HANYA boleh cocok dengan sediaan topikal.
   - Tetes/Salep Mata HANYA boleh dengan Mata. Tetes Telinga HANYA boleh dengan Telinga. Injeksi HANYA boleh dengan Injeksi.
   - DILARANG KERAS mencocokkan Tablet ke Sirup/Salep/Injeksi ataupun sebaliknya!
2. KEKUATAN DOSIS & VOLUME:
   - Dosis zat aktif (500mg, 250mg, 10mg, dll) WAJIB sesuai. Jangan memasangkan 500mg dengan 250mg.
   - Volume (60ml, 100ml, 15g, 5g) harus sesuai atau setara.
3. NAMA DAGANG & PBF INDONESIA:
   - Pahami awalan pabrik PBF (contoh: Alerzin = Hufalerzine [pabrik Hufa], Novamox = Amoxicillin [pabrik Nova], Yusimox = Amoxicillin [pabrik Ifars]).
   - Abaikan kode kemasan PBF seperti NORET, NORETUR, 'IFR', 'HUF', 'MET', 'DEX', '/60', '/80', dll.
   - Pahami singkatan umum (PCT = Paracetamol, GG = Guaifenesin, CTM = Chlorpheniramine, Asmef = Asam Mefenamat, dll).
4. JIKA TIDAK ADA YANG COCOK / BUKAN STOK PBF:
   - Jika semua kandidat memiliki zat aktif atau bentuk sediaan yang tidak cocok, ISI matched_id dengan null dan decision dengan "not_stocked" (jika produk alkes/non-obat) atau "unlisted" (jika obat belum ada di distributor). JANGAN ASAL PILIH!

Kembalikan HANYA format JSON valid berikut:
{
  "decision": "matched" | "not_stocked" | "unlisted" | "ambiguous",
  "matched_id": <id_produk_atau_null>,
  "confidence": <angka_0_sampai_100>,
  "reason": "<penjelasan pertimbangan: zat aktif, sediaan, dosis/volume>"
}
PROMPT;
    }
}

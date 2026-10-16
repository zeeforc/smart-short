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

    /**
     * Cocokkan banyak obat sekaligus dalam satu API call (batch matching).
     * Jauh lebih efisien daripada memanggil matchDrug() satu per satu.
     *
     * @param  array<int, array{raw_name: string, candidates: array<int, array{id: int, name: string, confidence: float}>}>  $items
     * @return array<string, array{decision: string, matched_id: ?int, confidence: float, reason: string}>
     */
    public function matchDrugsBatch(array $items): array
    {
        if (! $this->isAvailable() || empty($items)) {
            return [];
        }

        $count = count($items);
        $itemsText = '';
        foreach ($items as $i => $item) {
            $idx = $i + 1;
            $itemsText .= "Obat {$idx}: \"{$item['raw_name']}\"\n";
            foreach ($item['candidates'] as $cand) {
                $itemsText .= "  - ID:{$cand['id']} | {$cand['name']} (skor:{$cand['confidence']}%)\n";
            }
            $itemsText .= "\n";
        }

        $prompt = "Cocokkan {$count} obat defecta apotek Indonesia ke katalog PBF. Gunakan 4 kriteria ketat:\n"
            ."1. SEDIAAN: TAB/KAP/KAPL=solid | SYR/SUS=cair | SALEP/KRIM=topikal | INJ=injeksi. DILARANG silang sediaan.\n"
            ."2. DOSIS/VOLUME: harus kompatibel (mg, ml, gr).\n"
            ."3. NAMA DAGANG: abaikan suffix PBF (NORETUR, NORET, 'IFR', 'BER', 'GLX', /50, /100). Nama merek = produk sama.\n"
            ."4. TIDAK COCOK: gunakan \"ambiguous\" jika ragu, \"not_stocked\" jika alkes/non-obat.\n\n"
            ."{$itemsText}"
            ."Kembalikan HANYA JSON (satu hasil per nomor obat):\n"
            ."{\"results\":[{\"index\":1,\"decision\":\"matched\",\"matched_id\":<int_atau_null>,\"confidence\":<0-100>,\"reason\":\"<singkat>\"},...]}'\n"
            .'Decision valid: "matched" | "not_stocked" | "unlisted" | "ambiguous"';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout + ($count * 3))->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Kamu adalah sistem pencocokan obat farmasi Indonesia yang akurat. Selalu kembalikan JSON valid sesuai format yang diminta.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1,
                'max_tokens' => 150 + ($count * 80),
            ]);

            if (! $response->successful()) {
                Log::warning('AiDrugMatchingService batch match failed', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 300),
                    'items_count' => $count,
                ]);

                return [];
            }

            $json = $response->json();
            $content = $json['choices'][0]['message']['content'] ?? null;
            if (! $content) {
                return [];
            }

            $parsed = json_decode($content, true);
            if (! isset($parsed['results']) || ! is_array($parsed['results'])) {
                return [];
            }

            $output = [];
            foreach ($parsed['results'] as $result) {
                $idx = (int) ($result['index'] ?? 0) - 1;
                if (! isset($items[$idx])) {
                    continue;
                }

                $output[$items[$idx]['raw_name']] = [
                    'decision' => (string) ($result['decision'] ?? 'ambiguous'),
                    'matched_id' => isset($result['matched_id']) && is_numeric($result['matched_id'])
                        ? (int) $result['matched_id']
                        : null,
                    'confidence' => (float) ($result['confidence'] ?? 0),
                    'reason' => (string) ($result['reason'] ?? ''),
                ];
            }

            return $output;

        } catch (\Throwable $e) {
            Log::error('AiDrugMatchingService batch match exception: '.$e->getMessage(), [
                'items_count' => $count,
            ]);

            return [];
        }
    }

    /**
     * Klasifikasikan banyak nama produk tanpa kandidat (not_stocked vs unlisted) dalam satu API call.
     *
     * @param  array<int, string>  $rawNames
     * @return array<string, array{decision: string, confidence: float, reason: string}>
     */
    public function classifyUnmatchedBatch(array $rawNames): array
    {
        if (! $this->isAvailable() || empty($rawNames)) {
            return [];
        }

        $count = count($rawNames);
        $listText = '';
        foreach ($rawNames as $i => $name) {
            $listText .= ($i + 1).". \"{$name}\"\n";
        }

        $prompt = "Klasifikasikan {$count} item berikut dari pesanan apotek Indonesia (tidak ditemukan di katalog PBF):\n\n"
            ."{$listText}\n"
            ."Tentukan setiap item:\n"
            ."- \"not_stocked\": Alkes/non-obat (pantyliner, celana khitan, kasa, spuit, masker, handscoon, dll)\n"
            ."- \"unlisted\": Obat farmasi yang belum ada di distributor rekanan\n"
            ."- \"ambiguous\": Tidak dapat ditentukan\n\n"
            ."Kembalikan JSON:\n"
            .'{"results":[{"index":1,"decision":"not_stocked","confidence":95,"reason":"<singkat>"},...]}';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout + ($count * 2))->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Kamu adalah sistem klasifikasi produk farmasi apotek Indonesia. Kembalikan HANYA format JSON valid.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1,
                'max_tokens' => 80 + ($count * 50),
            ]);

            if (! $response->successful()) {
                Log::warning('AiDrugMatchingService classify batch failed', [
                    'status' => $response->status(),
                    'items_count' => $count,
                ]);

                return [];
            }

            $json = $response->json();
            $content = $json['choices'][0]['message']['content'] ?? null;
            if (! $content) {
                return [];
            }

            $parsed = json_decode($content, true);
            if (! isset($parsed['results']) || ! is_array($parsed['results'])) {
                return [];
            }

            $output = [];
            foreach ($parsed['results'] as $result) {
                $idx = (int) ($result['index'] ?? 0) - 1;
                if (! isset($rawNames[$idx])) {
                    continue;
                }

                $decision = (string) ($result['decision'] ?? 'ambiguous');
                if (! in_array($decision, ['not_stocked', 'unlisted', 'ambiguous'])) {
                    $decision = 'ambiguous';
                }

                $output[$rawNames[$idx]] = [
                    'decision' => $decision,
                    'confidence' => (float) ($result['confidence'] ?? 0),
                    'reason' => (string) ($result['reason'] ?? ''),
                ];
            }

            return $output;

        } catch (\Throwable $e) {
            Log::error('AiDrugMatchingService classify batch exception: '.$e->getMessage(), [
                'items_count' => $count,
            ]);

            return [];
        }
    }
}

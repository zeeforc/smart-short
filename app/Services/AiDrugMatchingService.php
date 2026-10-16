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
     * Mencocokkan 1 nama obat defecta dengan daftar kandidat produk dari master pricelist.
     *
     * @param  string  $rawName  Nama obat mentah dari defecta (contoh: "AMOX 500", "PCT SYR")
     * @param  Collection<int, NormalizedProduct>|array<int, mixed>  $candidates  Daftar kandidat produk dari database
     * @return array{
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
            return null;
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
                        'content' => 'Kamu adalah asisten apoteker ahli inventaris obat apotek di Indonesia. Tugasmu adalah mencocokkan nama obat dari daftar pesanan (defecta) dengan produk yang tepat di katalog pricelist distributor PBF. Keluarkan jawaban HANYA berupa objek JSON valid.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1,
                'max_tokens' => 300,
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

            return [
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
     * Membangun prompt kecocokan obat.
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

Aturan Penilaian:
1. Pahami singkatan lazim farmasi Indonesia (misal: PCT = Paracetamol, GG = Gliseril Guaiakolat, CTM = Chlorpheniramine, Amox = Amoxicillin, Asmef = Asam Mefenamat, dll).
2. Kekuatan dosis dan sediaan WAJIB kompatibel (jangan mencocokkan Tablet ke Salep/Sirup, atau 500mg ke 250mg kecuali memang tidak ada perbedaan signifikan).
3. Jika ada kandidat yang memang benar-benar obat yang dimaksud, berikan matched_id dengan confidence (80.0 - 100.0) dan alasan singkat.
4. Jika semua kandidat berbeda zat aktif atau bentuk sediaan sama sekali tidak cocok, isi matched_id dengan null dan confidence 0.

Kembalikan HANYA format JSON valid berikut tanpa markdown:
{
  "matched_id": <id_produk_atau_null>,
  "confidence": <angka_0_sampai_100>,
  "reason": "<alasan ringkas kenapa cocok atau kenapa ditolak>"
}
PROMPT;
    }
}

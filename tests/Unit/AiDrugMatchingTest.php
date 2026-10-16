<?php

namespace Tests\Unit;

use App\Models\NormalizedProduct;
use App\Services\AiDrugMatchingService;
use App\Services\DrugMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiDrugMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected DrugMatchingService $matcher;

    protected AiDrugMatchingService $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.groq.api_key', 'gsk_mock_test_key');
        $this->matcher = app(DrugMatchingService::class);
        $this->aiService = app(AiDrugMatchingService::class);
    }

    public function test_ai_service_reports_available_when_key_is_set(): void
    {
        $this->assertTrue($this->aiService->isAvailable());

        Config::set('services.groq.api_key', '');
        $serviceWithoutKey = new AiDrugMatchingService;
        $this->assertFalse($serviceWithoutKey->isAvailable());
    }

    public function test_ai_service_successfully_matches_drug_with_http_fake(): void
    {
        $target = NormalizedProduct::create([
            'normalized_name' => 'AMOXICILLIN|500MG|TAB',
            'parsed_name' => 'AMOXICILLIN',
            'parsed_strength' => '500MG',
            'parsed_form' => 'TAB',
            'lowest_price' => 500,
        ]);

        $other = NormalizedProduct::create([
            'normalized_name' => 'AMOXSAN|250MG|DRY SYRUP',
            'parsed_name' => 'AMOXSAN',
            'parsed_strength' => '250MG',
            'parsed_form' => 'SYR',
            'lowest_price' => 25000,
        ]);

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'matched_id' => $target->id,
                                'confidence' => 95.0,
                                'reason' => 'Amox 500 mengacu pada Amoxicillin 500 mg tablet',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->aiService->matchDrug('AMOX 500', collect([$target, $other]));

        $this->assertNotNull($result);
        $this->assertSame($target->id, $result['matched_id']);
        $this->assertSame(95.0, $result['confidence']);
        $this->assertStringContainsString('Amox 500 mengacu pada Amoxicillin', $result['reason']);
        $this->assertSame($target->id, $result['product']->id);
    }

    public function test_drug_matching_service_uses_ai_when_fuzzy_is_ambiguous(): void
    {
        // 2 varian dengan kemiripan nama yang sangat dekat (skor fuzzy tidak dominan)
        $cand1 = NormalizedProduct::create([
            'normalized_name' => 'ACETYLCYSTEINE APHARMA|200MG|KAP',
            'parsed_name' => 'ACETYLCYSTEINE APHARMA',
            'parsed_strength' => '200MG',
            'parsed_form' => 'KAP',
            'lowest_price' => 1200,
        ]);

        $cand2 = NormalizedProduct::create([
            'normalized_name' => 'ACETYLCYSTEINE DEXA|200MG|KAP',
            'parsed_name' => 'ACETYLCYSTEINE DEXA',
            'parsed_strength' => '200MG',
            'parsed_form' => 'KAP',
            'lowest_price' => 1100,
        ]);

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'matched_id' => $cand2->id,
                                'confidence' => 92.0,
                                'reason' => 'Cocok dengan Acetylcysteine Dexa 200mg',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $res = $this->matcher->match('ACETYLCYSTEINE DEXA 200');

        // Harus match via AI atau exact/auto
        $this->assertContains($res['status'], ['ai_matched', 'auto_matched', 'exact']);
        $this->assertNotNull($res['product']);

        if ($res['status'] === 'ai_matched') {
            $this->assertSame($cand2->id, $res['product']->id);
            $this->assertDatabaseHas('product_aliases', [
                'normalized_product_id' => $cand2->id,
                'target_canonical' => $cand2->normalized_name,
            ]);
        }
    }

    public function test_handles_abbreviation_like_pct_using_ai(): void
    {
        $paracetamol = NormalizedProduct::create([
            'normalized_name' => 'PARACETAMOL NOVELL|500MG|TAB',
            'parsed_name' => 'PARACETAMOL NOVELL',
            'parsed_strength' => '500MG',
            'parsed_form' => 'TAB',
            'lowest_price' => 450,
        ]);

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'matched_id' => $paracetamol->id,
                                'confidence' => 96.0,
                                'reason' => 'PCT 500 adalah singkatan dari Paracetamol 500mg',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $res = $this->matcher->match('PCT 500');

        $this->assertSame('ai_matched', $res['status']);
        $this->assertSame($paracetamol->id, $res['product']->id);
        $this->assertSame(96.0, $res['confidence']);
        $this->assertStringContainsString('Cocok via AI', $res['reason']);

        // Alias tersimpan secara permanen
        $this->assertDatabaseHas('product_aliases', [
            'normalized_product_id' => $paracetamol->id,
            'target_canonical' => $paracetamol->normalized_name,
        ]);
    }
}

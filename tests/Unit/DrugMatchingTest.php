<?php

namespace Tests\Unit;

use App\Filament\Pages\SmartDefecta;
use App\Models\NormalizedProduct;
use App\Models\ProductAlias;
use App\Models\Supplier;
use App\Services\DrugMatchingService;
use App\Services\OrderCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrugMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected DrugMatchingService $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = app(DrugMatchingService::class);
    }

    public function test_matches_exact_drug_name_and_strength(): void
    {
        $product = NormalizedProduct::create([
            'normalized_name' => 'PARACETAMOL|500MG|TAB',
            'parsed_name' => 'PARACETAMOL',
            'parsed_strength' => '500MG',
            'parsed_form' => 'TAB',
            'lowest_price' => 1000,
        ]);

        $res = $this->matcher->match('PARACETAMOL 500 MG TAB');

        $this->assertSame('exact', $res['status']);
        $this->assertEquals(100.0, $res['confidence']);
        $this->assertEquals($product->id, $res['product']->id);
    }

    public function test_auto_matches_swapped_word_order(): void
    {
        $product = NormalizedProduct::create([
            'normalized_name' => 'SANMOL SYR FORTE||SYR',
            'parsed_name' => 'SANMOL SYR FORTE',
            'parsed_strength' => null,
            'parsed_form' => 'SYR',
            'lowest_price' => 15000,
        ]);

        $res = $this->matcher->match('SANMOL FORTE SYR');

        $this->assertSame('auto_matched', $res['status']);
        $this->assertGreaterThanOrEqual(85.0, $res['confidence']);
        $this->assertEquals($product->id, $res['product']->id);

        $this->assertDatabaseHas('product_aliases', [
            'normalized_product_id' => $product->id,
        ]);
    }

    public function test_dosage_guard_rejects_different_strengths(): void
    {
        NormalizedProduct::create([
            'normalized_name' => 'AMLODIPINE|10MG|TAB',
            'parsed_name' => 'AMLODIPINE',
            'parsed_strength' => '10MG',
            'parsed_form' => 'TAB',
            'lowest_price' => 500,
        ]);

        $res = $this->matcher->match('AMLODIPINE 5 MG');

        $this->assertNotSame('exact', $res['status']);
        $this->assertNotSame('auto_matched', $res['status']);
        $this->assertNull($res['product']);
    }

    public function test_liquid_vs_solid_guard_prevents_syrup_matching_tablet(): void
    {
        NormalizedProduct::create([
            'normalized_name' => 'LERZIN TAB||TAB',
            'parsed_name' => 'LERZIN TAB',
            'parsed_strength' => null,
            'parsed_form' => 'TAB',
            'lowest_price' => 2000,
        ]);

        $res = $this->matcher->match('LERZIN SIRUP');

        $this->assertNotSame('auto_matched', $res['status']);
        $this->assertNull($res['product']);
    }

    public function test_uses_existing_alias_instantly(): void
    {
        $product = NormalizedProduct::create([
            'normalized_name' => 'AMOXICILLIN|500MG|KAP',
            'parsed_name' => 'AMOXICILLIN',
            'parsed_strength' => '500MG',
            'parsed_form' => 'KAP',
            'lowest_price' => 3000,
        ]);

        ProductAlias::create([
            'normalized_product_id' => $product->id,
            'alias_raw' => 'amoxicilin kf 500',
            'alias_normalized' => 'AMOXICILIN KF|500MG|',
        ]);

        $res = $this->matcher->match('amoxicilin kf 500');

        $this->assertSame('alias', $res['status']);
        $this->assertEquals(100.0, $res['confidence']);
        $this->assertEquals($product->id, $res['product']->id);
    }

    public function test_matches_minor_spelling_typo(): void
    {
        $product = NormalizedProduct::create([
            'normalized_name' => 'AMOXICILLIN|500MG|KAP',
            'parsed_name' => 'AMOXICILLIN',
            'parsed_strength' => '500MG',
            'parsed_form' => 'KAP',
            'lowest_price' => 3000,
        ]);

        $res = $this->matcher->match('AMOXICILIN 500MG');

        $this->assertSame('auto_matched', $res['status']);
        $this->assertEquals($product->id, $res['product']->id);
        $this->assertGreaterThanOrEqual(85.0, $res['confidence']);
    }

    public function test_recognizes_not_stocked_alias_instantly(): void
    {
        ProductAlias::create([
            'normalized_product_id' => null,
            'alias_raw' => 'AVAIL PANTYLINER HIJAU',
            'alias_normalized' => 'AVAIL PANTYLINER HIJAU||',
            'status' => 'not_stocked',
        ]);

        $res = $this->matcher->match('AVAIL PANTYLINER HIJAU');

        $this->assertSame('not_stocked', $res['status']);
        $this->assertEquals(100.0, $res['confidence']);
        $this->assertNull($res['product']);
    }

    public function test_recognizes_unlisted_alias_instantly(): void
    {
        ProductAlias::create([
            'normalized_product_id' => null,
            'alias_raw' => 'OBAT BARU BELUM ADA PBF',
            'alias_normalized' => 'OBAT BARU BELUM ADA PBF||',
            'status' => 'unlisted',
        ]);

        $res = $this->matcher->match('OBAT BARU BELUM ADA PBF');

        $this->assertSame('unlisted', $res['status']);
        $this->assertEquals(100.0, $res['confidence']);
        $this->assertNull($res['product']);
    }

    public function test_does_not_suggest_completely_different_drug_below_threshold(): void
    {
        NormalizedProduct::create([
            'normalized_name' => 'LINOGRA GRAHA FARMA|100MG|TAB',
            'parsed_name' => 'LINOGRA GRAHA FARMA',
            'parsed_strength' => '100MG',
            'parsed_form' => 'TAB',
            'lowest_price' => 25000,
        ]);

        $res = $this->matcher->match('AVAIL PANTYLINER HIJAU');

        $this->assertSame('unmatched', $res['status']);
        $this->assertNull($res['product']);
        $this->assertEmpty($res['suggestions']);
    }

    public function test_matches_and_counts_multiple_drug_variants_such_as_arkavit(): void
    {
        NormalizedProduct::create([
            'normalized_name' => 'ARKAVIT KAPL. BALATIF||KAP',
            'parsed_name' => 'ARKAVIT KAPL. BALATIF',
            'parsed_strength' => null,
            'parsed_form' => 'KAP',
            'lowest_price' => 30000,
        ]);

        NormalizedProduct::create([
            'normalized_name' => 'ARKAVIT-C KAPL. BALATIF||KAP',
            'parsed_name' => 'ARKAVIT-C KAPL. BALATIF',
            'parsed_strength' => null,
            'parsed_form' => 'KAP',
            'lowest_price' => 35000,
        ]);

        NormalizedProduct::create([
            'normalized_name' => "ARKAVIT KAPLET 'BALATIF'/50||KAP",
            'parsed_name' => "ARKAVIT KAPLET 'BALATIF'/50",
            'parsed_strength' => null,
            'parsed_form' => 'KAP',
            'lowest_price' => 28000,
        ]);

        $res = $this->matcher->match('ARKAVIT KAPLET');

        $this->assertNotEmpty($res['suggestions']);
        $this->assertGreaterThanOrEqual(2, $res['total_candidates']);
        $this->assertGreaterThanOrEqual(80.0, $res['suggestions'][0]['confidence']);
        $this->assertStringContainsString('ARKAVIT', $res['suggestions'][0]['name']);
    }

    public function test_rejects_different_brand_such_as_allergen_vs_alletrol(): void
    {
        NormalizedProduct::create([
            'normalized_name' => 'ALLETROL SALEP MATA||OINT',
            'parsed_name' => 'ALLETROL SALEP MATA',
            'parsed_strength' => null,
            'parsed_form' => 'OINT',
            'lowest_price' => 20000,
        ]);

        $res = $this->matcher->match('ALLERGEN TAB');

        $this->assertSame('unmatched', $res['status']);
        $this->assertNull($res['product']);
        $this->assertEmpty($res['suggestions']);
    }

    public function test_rejects_different_brand_such_as_alerzin_vs_alerten(): void
    {
        NormalizedProduct::create([
            'normalized_name' => 'ALERTEN Q TAB 100MG||TAB',
            'parsed_name' => 'ALERTEN Q TAB',
            'parsed_strength' => '100MG',
            'parsed_form' => 'TAB',
            'lowest_price' => 85000,
        ]);

        $res = $this->matcher->match('ALERZIN TAB');

        $this->assertSame('unmatched', $res['status']);
        $this->assertNull($res['product']);
        $this->assertEmpty($res['suggestions']);
    }

    public function test_matches_same_brand_such_as_alerhis(): void
    {
        $product = NormalizedProduct::create([
            'normalized_name' => 'ALERHIS KAPLET LAPI 10MG||KAP',
            'parsed_name' => "ALERHIS KAPLET 'LAPI'",
            'parsed_strength' => '10MG',
            'parsed_form' => 'KAP',
            'lowest_price' => 12000,
        ]);

        $res = $this->matcher->match('ALERHIS TAB');

        $this->assertNotEmpty($res['suggestions']);
        $this->assertGreaterThanOrEqual(75.0, $res['suggestions'][0]['confidence']);
        $this->assertStringContainsString('ALERHIS', $res['suggestions'][0]['name']);
    }

    public function test_are_equivalent_detects_same_drug_with_different_supplier_naming(): void
    {
        $p1 = NormalizedProduct::create([
            'normalized_name' => "AMPICILLIN 'NOVA'/100|500MG|KAP",
            'parsed_name' => "AMPICILLIN 'NOVA'/100",
            'parsed_strength' => '500MG',
            'parsed_form' => 'KAP',
            'lowest_price' => 45000,
        ]);

        $p2 = NormalizedProduct::create([
            'normalized_name' => 'AMPICILLIN GEN. NOVA NOVAPHARIN|500MG|TAB',
            'parsed_name' => 'AMPICILLIN GEN. NOVA NOVAPHARIN',
            'parsed_strength' => '500MG',
            'parsed_form' => 'TAB',
            'lowest_price' => 38000,
        ]);

        $pOther = NormalizedProduct::create([
            'normalized_name' => 'AMPICILLIN GEN. SELES SELES|500MG|TAB',
            'parsed_name' => 'AMPICILLIN GEN. SELES SELES',
            'parsed_strength' => '500MG',
            'parsed_form' => 'TAB',
            'lowest_price' => 36000,
        ]);

        $this->assertTrue($this->matcher->areEquivalent($p1, $p2));
        $this->assertFalse($this->matcher->areEquivalent($p1, $pOther));
    }

    public function test_order_calculation_pools_different_supplier_variants(): void
    {
        $supA = Supplier::create([
            'name' => 'PBF DISTRIBUTOR A',
            'is_active' => true,
            'is_ppn_included' => true,
        ]);

        $supB = Supplier::create([
            'name' => 'PBF DISTRIBUTOR B',
            'is_active' => true,
            'is_ppn_included' => true,
        ]);

        $p1 = NormalizedProduct::create([
            'normalized_name' => "AMPICILLIN 'NOVA'/100|500MG|KAP",
            'parsed_name' => "AMPICILLIN 'NOVA'/100",
            'parsed_strength' => '500MG',
            'parsed_form' => 'KAP',
            'lowest_price' => 45000,
            'price_history_json' => [
                [
                    'supplier_id' => $supA->id,
                    'raw_name' => "AMPICILLIN 'NOVA'/100 500MG",
                    'price' => 45000,
                    'unit' => 'BOX',
                ],
            ],
        ]);

        $p2 = NormalizedProduct::create([
            'normalized_name' => 'AMPICILLIN GEN. NOVA NOVAPHARIN|500MG|TAB',
            'parsed_name' => 'AMPICILLIN GEN. NOVA NOVAPHARIN',
            'parsed_strength' => '500MG',
            'parsed_form' => 'TAB',
            'lowest_price' => 38000,
            'price_history_json' => [
                [
                    'supplier_id' => $supB->id,
                    'raw_name' => 'AMPICILLIN GEN. NOVA NOVAPHARIN 500MG',
                    'price' => 38000,
                    'unit' => 'BOX',
                ],
            ],
        ]);

        // Cocokkan manual defecta ke P1
        ProductAlias::create([
            'alias_raw' => 'AMPICILIN 500 MG TAB NOVA',
            'alias_normalized' => 'AMPICILIN TAB NOVA|500MG|',
            'normalized_product_id' => $p1->id,
            'status' => 'matched',
        ]);

        $calcService = app(OrderCalculationService::class);
        $result = $calcService->calculateBestSupplier('AMPICILIN 500 MG TAB NOVA', 1);

        // Kedua supplier harus terdeteksi dan Supplier B (Rp 38.000) yang harus menang!
        $this->assertNotNull($result['winner']);
        $this->assertEquals($supB->id, $result['winner']['supplier_id']);
        $this->assertEquals(38000.0, $result['winner']['base_price']);
        $this->assertCount(2, $result['comparisons']);
    }

    public function test_order_calculation_service_uses_cache_and_calculates_correctly(): void
    {
        $supplier = Supplier::create([
            'name' => 'DISTRIBUTOR A',
            'is_active' => true,
            'is_ppn_included' => true,
        ]);

        $product = NormalizedProduct::create([
            'normalized_name' => 'PARACETAMOL|500MG|TAB',
            'parsed_name' => 'PARACETAMOL',
            'parsed_strength' => '500MG',
            'parsed_form' => 'TAB',
            'lowest_price' => 10000,
            'price_history_json' => [
                [
                    'supplier_id' => $supplier->id,
                    'raw_name' => 'PARACETAMOL 500MG',
                    'price' => 10000,
                    'unit' => 'BOX',
                ],
            ],
        ]);

        $calcService = app(OrderCalculationService::class);

        $res1 = $calcService->calculateBestSupplier('PARACETAMOL 500MG', 1);
        $this->assertNotNull($res1['winner']);
        $this->assertEquals(10000.0, $res1['winner']['final_price']);

        $res2 = $calcService->calculateBestSupplier('PARACETAMOL 500MG', 2);
        $this->assertNotNull($res2['winner']);
        $this->assertEquals(10000.0, $res2['winner']['final_price']);
    }

    public function test_batch_matching_saves_multiple_aliases(): void
    {
        $supplier = Supplier::create([
            'name' => 'DISTRIBUTOR A',
            'is_active' => true,
            'is_ppn_included' => true,
        ]);

        $p1 = NormalizedProduct::create([
            'normalized_name' => 'BIOLYSIN APPETITE SYR|60ML|SYR',
            'parsed_name' => 'BIOLYSIN APPETITE SYR',
            'parsed_strength' => '60ML',
            'parsed_form' => 'SYR',
            'lowest_price' => 15000,
        ]);

        $page = new SmartDefecta;

        $decisions = [
            [
                'raw_name' => 'BIOLYSIN SYR 60 ML',
                'action_type' => 'match',
                'normalized_product_id' => $p1->id,
            ],
            [
                'raw_name' => 'BALSEM GELIGA 40 GR',
                'action_type' => 'not_stocked',
                'normalized_product_id' => null,
            ],
            [
                'raw_name' => 'OBAT LANGKA 100MG',
                'action_type' => 'unlisted',
                'normalized_product_id' => null,
            ],
            [
                'raw_name' => 'OBAT DILEWATI',
                'action_type' => 'skip',
                'normalized_product_id' => null,
            ],
        ];

        $page->saveBatchMatching($decisions);

        $this->assertDatabaseHas('product_aliases', [
            'alias_raw' => 'BIOLYSIN SYR 60 ML',
            'status' => 'matched',
            'normalized_product_id' => $p1->id,
        ]);

        $this->assertDatabaseHas('product_aliases', [
            'alias_raw' => 'BALSEM GELIGA 40 GR',
            'status' => 'not_stocked',
            'normalized_product_id' => null,
        ]);

        $this->assertDatabaseHas('product_aliases', [
            'alias_raw' => 'OBAT LANGKA 100MG',
            'status' => 'unlisted',
            'normalized_product_id' => null,
        ]);

        $this->assertDatabaseMissing('product_aliases', [
            'alias_raw' => 'OBAT DILEWATI',
        ]);
    }

    public function test_search_normalized_products_returns_matching_results(): void
    {
        NormalizedProduct::create([
            'normalized_name' => 'BIOLYSIN SMART SYR|60ML|SYR',
            'parsed_name' => 'BIOLYSIN SMART SYR',
            'parsed_strength' => '60ML',
            'parsed_form' => 'SYR',
            'lowest_price' => 18000,
        ]);

        $page = new SmartDefecta;
        $results = $page->searchNormalizedProducts('biolysin 60ml');

        $this->assertNotEmpty($results);
        $this->assertStringContainsString('BIOLYSIN SMART SYR', $results[0]['name']);
    }
}

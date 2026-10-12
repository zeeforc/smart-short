<?php

namespace Tests\Unit;

use App\Models\NormalizedProduct;
use App\Models\ProductAlias;
use App\Services\DrugMatchingService;
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
}

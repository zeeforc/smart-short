<?php

namespace Tests\Unit;

use App\Imports\RawProductImport;
use App\Imports\SmartDefectaImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PriceParsingTest extends TestCase
{
    use RefreshDatabase;

    protected RawProductImport $import;

    protected function setUp(): void
    {
        parent::setUp();
        $this->import = new RawProductImport(1, 1);
    }

    protected function callCleanNumber($value): float
    {
        $reflection = new \ReflectionClass($this->import);
        $method = $reflection->getMethod('cleanNumber');
        $method->setAccessible(true);

        return $method->invoke($this->import, $value);
    }

    public function test_parses_native_floats_and_integers_accurately(): void
    {
        $this->assertEquals(4450.545, $this->callCleanNumber(4450.545));
        $this->assertEquals(15000.0, $this->callCleanNumber(15000));
        $this->assertEquals(447638.025, $this->callCleanNumber(447638.025));
    }

    public function test_does_not_inflate_three_decimal_float_strings(): void
    {
        // Formula results in Excel (e.g. HNA * 1.11 * disc) produce strings like '4450.545'
        // Previously this was parsed as 4450545 (Rp 4.45 Juta) because of .\\d{3}$ match!
        $this->assertEquals(4450.545, $this->callCleanNumber('4450.545'));
        $this->assertEquals(29761.875, $this->callCleanNumber('29761.875'));
        $this->assertEquals(447638.025, $this->callCleanNumber('447638.025'));
        $this->assertEquals(109505.607, $this->callCleanNumber('109505.607'));
    }

    public function test_parses_indonesian_thousand_separator_properly(): void
    {
        $this->assertEquals(15000.0, $this->callCleanNumber('15.000'));
        $this->assertEquals(250000.0, $this->callCleanNumber('250.000'));
        $this->assertEquals(1500000.0, $this->callCleanNumber('1.500.000'));
        $this->assertEquals(15000.0, $this->callCleanNumber('Rp 15.000'));
    }

    public function test_parses_us_format_properly(): void
    {
        $this->assertEquals(12000.50, $this->callCleanNumber('12,000.50'));
        $this->assertEquals(10500.0, $this->callCleanNumber('10,500'));
    }

    public function test_smart_defecta_does_not_treat_price_columns_as_qty(): void
    {
        $import = new SmartDefectaImport;
        $rows = new Collection([
            // Header with both 'Qty' and 'Jumlah Harga'
            collect(['No', 'Nama Obat', 'Qty', 'Satuan', 'Harga Satuan', 'Jumlah Harga']),
            collect([1, 'PARACETAMOL 500MG', 10, 'STRIP', 5000, 50000]),
            collect([2, 'AMOXICILLIN 500MG', 5, 'BOX', 25000, 125000]),
        ]);

        $import->collection($rows);

        $this->assertCount(2, $import->data);
        $this->assertEquals(10, $import->data['paracetamol 500mg']['qty']);
        $this->assertEquals(5, $import->data['amoxicillin 500mg']['qty']);
    }
}

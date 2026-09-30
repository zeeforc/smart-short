<?php

namespace App\Exports;

use App\Models\NormalizedProduct;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class NormalizedProductsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    protected $supplier_id;
    protected $suppliers;

    public function __construct($supplier_id = null)
    {
        $this->supplier_id = $supplier_id;
        $this->suppliers = \App\Models\Supplier::pluck('name', 'id')->toArray();
    }

    public function collection(): \Illuminate\Support\Collection
    {
        $query = NormalizedProduct::with('supplier');
        
        if ($this->supplier_id) {
            $query->where('best_supplier_id', $this->supplier_id);
        }
        
        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'NAMA OBAT (HASIL SORTIR)',
            'HARGA TERMURAH',
            'SUPPLIER / SALES TERBAIK',
            'DETAIL PERBANDINGAN HARGA SALES LAIN'
        ];
    }

    public function map($product): array
    {
        $historyText = '';
        if ($product->price_history_json) {
            $history = is_string($product->price_history_json) ? json_decode($product->price_history_json, true) : $product->price_history_json;
            
            $historyLines = [];
            foreach ($history as $h) {
                $supplierName = $this->suppliers[$h['supplier_id']] ?? 'Sales ID ' . $h['supplier_id'];
                $priceRp = 'Rp ' . number_format((float) $h['price'], 0, ',', '.');
                $historyLines[] = "- {$supplierName} : {$priceRp} ({$h['raw_name']} - {$h['unit']})";
            }
            $historyText = implode("\n", $historyLines);
        }

        return [
            $product->id,
            $product->normalized_name,
            $product->lowest_price,
            $product->supplier ? $product->supplier->name : 'Unknown',
            $historyText,
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        // Styling Header (Baris 1) biar tebal dan ada warnanya
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'], // Putih
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1F2937'], // Abu-abu gelap / Slate
            ],
        ]);
        
        // Wrap text biar kolom komparasi (E) bisa turun ke bawah per baris (multiline)
        $sheet->getStyle('E')->getAlignment()->setWrapText(true);
        
        // Set alignment semua cell ke atas (Top)
        $sheet->getStyle('A:E')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        return [];
    }
    
    public function columnFormats(): array
    {
        return [
            // Format kolom C (Harga Termurah) jadi format Rupiah native Excel
            'C' => '"Rp "#,##0_-',
        ];
    }
}

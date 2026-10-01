<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SmartDefectaExport implements FromArray, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected array $results;

    public function __construct(array $results)
    {
        // Flatten and sort by supplier name so they are grouped together
        $exportData = [];
        foreach ($results as $res) {
            if (isset($res['calculation']['winner'])) {
                $exportData[] = [
                    'request' => $res['request'],
                    'winner' => $res['calculation']['winner'],
                ];
            }
        }
        
        usort($exportData, function ($a, $b) {
            return strcmp($a['winner']['supplier_name'], $b['winner']['supplier_name']);
        });

        $this->results = $exportData;
    }

    public function array(): array
    {
        return $this->results;
    }

    public function headings(): array
    {
        return [
            'Supplier Pemenang',
            'Nama Obat (Request)',
            'QTY',
            'Harga Dasar (Rp)',
            'Diskon (%)',
            'PPN 12%',
            'Harga Final / Item (Rp)',
            'Total Harga (Rp)'
        ];
    }

    public function map($row): array
    {
        $winner = $row['winner'];
        $request = $row['request'];
        $qty = (int) $request['qty'];
        
        $finalPrice = $winner['final_price'];
        $totalPrice = $finalPrice * $qty;

        return [
            $winner['supplier_name'],
            $request['product_name'],
            $qty,
            $winner['base_price'],
            $winner['discount_pct'] . '%',
            $winner['is_ppn_included'] ? 'Termasuk' : '+12%',
            $finalPrice,
            $totalPrice
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

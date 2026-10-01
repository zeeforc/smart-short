<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SmartDefectaExport implements FromArray, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected array $results;

    public function __construct(array $results)
    {
        // Flatten and sort by supplier name so they are grouped together
        $exportData = [];
        foreach ($results as $res) {
            $exportData[] = [
                'request' => $res['request'],
                'winner' => $res['calculation']['winner'] ?? null,
            ];
        }

        usort($exportData, function ($a, $b) {
            $nameA = $a['winner']['supplier_name'] ?? 'ZZZ_BELUM_ADA_PRICELIST';
            $nameB = $b['winner']['supplier_name'] ?? 'ZZZ_BELUM_ADA_PRICELIST';

            return strcmp($nameA, $nameB);
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
            'Harga Netto/Item (Rp)',
            'PPN 12%',
            'Harga Final/Item (Rp)',
            'Total Harga (Rp)',
        ];
    }

    public function map($row): array
    {
        $winner = $row['winner'];
        $request = $row['request'];
        $qty = (int) $request['qty'];

        if (! $winner) {
            return [
                'Belum Ada Pricelist',
                $request['product_name'],
                $qty,
                '-',
                '-',
                '-',
                '-',
                '-',
                '-',
            ];
        }

        $netPrice = round($winner['net_price'], 2);
        $finalPrice = round($winner['final_price'], 2);
        $ppnAmount = $winner['is_ppn_included'] ? 0 : round($finalPrice - $netPrice, 2);
        $totalPrice = round($finalPrice * $qty, 2);

        return [
            $winner['supplier_name'],
            $request['product_name'],
            $qty,
            $winner['base_price'],
            $winner['discount_pct'].'%',
            $netPrice,
            $ppnAmount,
            $finalPrice,
            $totalPrice,
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

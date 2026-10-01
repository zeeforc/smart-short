<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class SmartDefectaImport implements ToCollection
{
    public array $data = [];

    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection): void
    {
        foreach ($collection as $index => $row) {
            $values = array_values($row->toArray());
            
            // Skip empty rows
            if (empty(array_filter($values))) continue;

            $col1 = (string) ($values[0] ?? '');
            $col2 = (string) ($values[1] ?? '');
            $col3 = (string) ($values[2] ?? '');
            
            // Basic header detection on first row
            if ($index === 0) {
                $headerStr = strtolower($col1 . ' ' . $col2 . ' ' . $col3);
                if (str_contains($headerStr, 'nama') || str_contains($headerStr, 'obat') || str_contains($headerStr, 'barang') || str_contains($headerStr, 'qty') || str_contains($headerStr, 'jumlah')) {
                    continue;
                }
            }

            $productName = null;
            $qty = 1;

            // Find product name (longest string that is not purely numeric)
            foreach ($values as $val) {
                $valStr = (string) $val;
                if (!is_numeric($valStr) && strlen(trim($valStr)) > 2) {
                    if (!$productName || strlen(trim($valStr)) > strlen($productName)) {
                        $productName = trim($valStr);
                    }
                }
            }

            // Find qty (first numeric value > 0)
            foreach ($values as $val) {
                if (is_numeric($val) && $val > 0) {
                    $qty = (int) $val;
                    break;
                }
            }

            if ($productName) {
                $this->data[] = [
                    'product_name' => $productName,
                    'qty' => $qty ?: 1,
                ];
            }
        }
    }
}

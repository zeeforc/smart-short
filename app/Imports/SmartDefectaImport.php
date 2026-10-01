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
        $nameIndex = -1;
        $qtyIndex = -1;
        $dataStarted = false;

        foreach ($collection as $index => $row) {
            $values = array_values($row->toArray());
            
            // Skip purely empty rows
            if (empty(array_filter($values, fn($v) => $v !== null && trim($v) !== ''))) {
                continue;
            }

            // Look for header row if we haven't found data yet
            if (!$dataStarted) {
                $rowStr = strtolower(implode(' ', $values));
                
                // If it's a title row, just skip
                if (str_contains($rowStr, 'smart defecta') || str_contains($rowStr, 'tanggal cek') || str_contains($rowStr, 'laporan')) {
                    continue;
                }

                // Check if this row looks like a header (contains nama/obat AND qty/jumlah)
                $hasNameHeader = false;
                $hasQtyHeader = false;
                
                foreach ($values as $colIdx => $val) {
                    $valStr = strtolower(trim((string) $val));
                    if (str_contains($valStr, 'nama') || str_contains($valStr, 'obat') || str_contains($valStr, 'produk') || str_contains($valStr, 'barang') || str_contains($valStr, 'item')) {
                        $nameIndex = $colIdx;
                        $hasNameHeader = true;
                    } elseif (str_contains($valStr, 'qty') || str_contains($valStr, 'jumlah') || str_contains($valStr, 'restock') || str_contains($valStr, 'pesan')) {
                        $qtyIndex = $colIdx;
                        $hasQtyHeader = true;
                    }
                }

                if ($hasNameHeader || $hasQtyHeader) {
                    $dataStarted = true;
                    continue; // Skip the header row itself
                }

                // If we hit a row that seems like actual data without a clear header, we start parsing
                // e.g. column 1 is string, column 2 is number. But we might accidentally grab title rows.
                // If we don't know, we assume No, Name, Qty format if it starts with a number.
            }

            // Parsing data row
            if (!$dataStarted && $nameIndex === -1 && $qtyIndex === -1) {
                // If we still don't know indices, let's guess from the first data row
                // Usually [0] => No, [1] => Name, [2] => Qty
                // Or [0] => Name, [1] => Qty
                if (is_numeric($values[0] ?? null) && !empty($values[1]) && is_numeric($values[2] ?? null)) {
                    $nameIndex = 1;
                    $qtyIndex = 2;
                } else {
                    $nameIndex = 0;
                    $qtyIndex = 1;
                }
                $dataStarted = true;
            }

            if ($dataStarted) {
                // Get the product name and qty based on detected indices
                $productName = trim((string) ($values[$nameIndex] ?? ''));
                $qtyVal = $values[$qtyIndex] ?? null;

                // If Name is empty, skip
                if (empty($productName)) {
                    continue;
                }

                // Clean Qty
                $qty = 1;
                if (is_numeric($qtyVal) && $qtyVal > 0) {
                    $qty = (int) $qtyVal;
                } else {
                    // Fallback to searching for numeric qty in the row if the index failed
                    foreach ($values as $idx => $val) {
                        if ($idx !== $nameIndex && $idx !== 0 && is_numeric($val) && $val > 0) {
                            $qty = (int) $val;
                            break;
                        }
                    }
                }

                $this->data[] = [
                    'product_name' => $productName,
                    'qty' => $qty,
                ];
            }
        }
    }
}

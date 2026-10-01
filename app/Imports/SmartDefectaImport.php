<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SmartDefectaImport implements ToCollection, WithHeadingRow
{
    public array $data = [];

    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection): void
    {
        foreach ($collection as $row) {
            $this->data[] = [
                'product_name' => $row['nama_obat'] ?? $row['nama'] ?? $row['obat'] ?? $row['product_name'] ?? null,
                'qty' => $row['jumlah'] ?? $row['qty'] ?? $row['quantity'] ?? 1,
            ];
        }
    }
}

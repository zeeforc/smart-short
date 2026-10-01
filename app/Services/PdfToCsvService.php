<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

class PdfToCsvService
{
    /**
     * Converts a specific pharmacy PDF format to a temporary CSV file.
     * Returns the relative path to the new CSV file in storage.
     */
    public function convert($pdfPath)
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($pdfPath);
        $text = $pdf->getText();

        $lines = explode("\n", $text);

        $csvData = [];
        // Header
        $csvData[] = ['NAMA BARANG', 'SATUAN', 'STOK', 'HARGA', 'DISKON'];

        foreach ($lines as $line) {
            $line = str_replace("\t", ' ', $line);

            // Regex to catch standard row format in PDF:
            // [Name and Factory] [Unit] [Stock] [Price] [Discount]
            if (preg_match('/^(.*?)\s*([A-Z\s\&\.\-\/]+?)(BOX|FLS|STR|BTL|TUBE|KAP|TAB|PCS|BOTOL|VIAL|AMP|POT|ZAK|GALON|ROL|ROLL|SET)\s+([\d,.]+)\s+([\d,.]+)\s*(\d*)$/i', $line, $m)) {
                $nama = trim($m[1].' '.$m[2]);
                $satuan = strtoupper(trim($m[3]));
                $stok = str_replace(',', '', $m[4]);
                $harga = str_replace(',', '', $m[5]);
                $diskon = trim($m[6]);

                $csvData[] = [$nama, $satuan, $stok, $harga, $diskon];
            }
        }

        // Create temporary CSV file
        $fileName = 'converted_pdf_'.Str::random(10).'.csv';
        $fullPath = Storage::disk('local')->path($fileName);

        // Ensure directory exists
        if (! file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        $fp = fopen($fullPath, 'w');
        foreach ($csvData as $fields) {
            fputcsv($fp, $fields);
        }
        fclose($fp);

        return $fileName;
    }
}

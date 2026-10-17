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
            $line = trim(preg_replace('/\s+/', ' ', $line));

            // Bersihkan tanda bintang penanda stok/retur (contoh di LJS: "13,123 * 22.5")
            $cleanedLine = preg_replace('/\s+\*\s+/', ' ', $line);

            // 1. Cek format 5 kolom: [Nama] [Satuan] [Stok] [Harga] [Diskon]
            if (preg_match('/^(.*?)\s*([A-Z\s\&\.\-\/]*?)(BOX[0-9]*|TUBE[,0-9A-Z]*|BTL[,0-9A-Z]*|FLS[0-9A-Z]*|STR|KAP|TAB|PCS|BOTOL|VIAL|AMP|POT|ZAK|GALON|ROL|ROLL|SET)\s+([\d,.]+)\s+([\d,.]+)\s+([\d,.]+)$/i', $cleanedLine, $m)) {
                $rawNama = trim($m[1].' '.$m[2]);
                $satuan = strtoupper(trim($m[3]));
                $stok = str_replace(',', '', $m[4]);
                $harga = str_replace(',', '', $m[5]);
                $diskon = trim($m[6]);

                // Bersihkan nomor urut baris di awal nama (contoh: "7 ACIFAR", "1465 NEBACETINE")
                $nama = preg_replace('/^\d{1,5}[\.\)\-\s]+\s*/', '', $rawNama);
                $nama = preg_replace('/^\d{2,5}(?=[a-zA-Z])/', '', $nama);
                $nama = trim(preg_replace('/\s+/', ' ', $nama));

                $csvData[] = [$nama, $satuan, $stok, $harga, $diskon];
            }
            // 2. Cek format 4 kolom (seperti LJS): [Nama] [Satuan] [Harga] [Diskon]
            elseif (preg_match('/^(.*?)\s*([A-Z\s\&\.\-\/]*?)(BOX[0-9]*|TUBE[,0-9A-Z]*|BTL[,0-9A-Z]*|FLS[0-9A-Z]*|STR|KAP|TAB|PCS|BOTOL|VIAL|AMP|POT|ZAK|GALON|ROL|ROLL|SET)\s+([\d,.]+)\s+([\d,.]+)$/i', $cleanedLine, $m)) {
                $rawNama = trim($m[1].' '.$m[2]);
                $satuan = strtoupper(trim($m[3]));
                $harga = str_replace(',', '', $m[4]);
                $diskon = trim($m[5]);
                $stok = 0;

                // Bersihkan nomor urut baris di awal nama
                $nama = preg_replace('/^\d{1,5}[\.\)\-\s]+\s*/', '', $rawNama);
                $nama = preg_replace('/^\d{2,5}(?=[a-zA-Z])/', '', $nama);
                $nama = trim(preg_replace('/\s+/', ' ', $nama));

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

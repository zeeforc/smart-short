<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithLimit;

class HeaderFinderImport implements ToCollection, WithLimit
{
    public $headerRow = 1;

    public $supplierName = null;

    public $companyName = null;

    public $contactPerson = null;

    public $phone = null;

    protected $fileName = '';

    public function __construct($fileName = '')
    {
        $this->fileName = $fileName;

        // Coba ekstrak dari nama file dulu
        $this->extractInfoFromText($this->fileName);
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $filtered = array_filter($row->toArray());

            // 1. Cari info Company, Sales, dan Phone dari baris-baris awal (Kop Surat)
            $previousCell = '';
            foreach ($filtered as $cell) {
                $cellStr = trim((string) $cell);

                // Cek isi sel ini sendirian
                $this->extractInfoFromText($cellStr);

                // Cek gabungan sel sebelumnya dan sel ini
                // Berguna untuk Excel yang formatnya terpisah: Sel A = "Sales", Sel B = "Budi"
                if ($previousCell) {
                    $this->extractInfoFromText($previousCell.' '.$cellStr);

                    // Penanganan khusus jika sel sebelumnya persis tertulis "Nama", "Sales", dsb.
                    $prevLower = strtolower($previousCell);
                    if (in_array($prevLower, ['nama', 'sales', 'nama sales', 'pic', 'contact', 'oleh'])) {
                        // Pastikan sel sebelahnya bukan nama PT/Obat/Tanggal
                        if (! preg_match('/(PT|CV|PBF|UD|Obat|Produk|Barang|Tanggal)/i', $cellStr) && ! $this->contactPerson) {
                            // Mencegah tulisan panjang aneh masuk
                            if (strlen($cellStr) < 30) {
                                $this->contactPerson = $this->cleanExtractedText($cellStr);
                            }
                        }
                    }
                }

                $previousCell = $cellStr;
            }

            // 2. Lanjut cari letak baris judul (Header)
            if (count($filtered) >= 3) {
                $hasNama = false;
                $hasHarga = false;

                foreach ($filtered as $cell) {
                    $cellStr = strtolower(trim((string) $cell));

                    if (in_array($cellStr, ['nama obat', 'nama produk', 'nama item', 'nama barang', 'nama', 'obat', 'produk', 'barang', 'deskripsi', 'item']) ||
                        str_contains($cellStr, 'nama ') ||
                        str_contains($cellStr, 'produk') ||
                        str_contains($cellStr, 'barang') ||
                        str_contains($cellStr, 'deskripsi')) {
                        $hasNama = true;
                    }

                    if (in_array($cellStr, ['harga', 'harga satuan', 'harga grosir', 'hna', 'price', 'modal', 'hpp', 'hrg']) ||
                        str_contains($cellStr, 'harga') ||
                        str_contains($cellStr, 'price') ||
                        str_contains($cellStr, 'hna')) {
                        $hasHarga = true;
                    }
                }

                if ($hasNama && $hasHarga) {
                    $this->headerRow = $index + 1;
                    break;
                }
            }
        }

        // Penentuan 'name' utama untuk Supplier
        // Prioritaskan Nama Perusahaan, kalau gak ada pakai Nama Sales
        $this->supplierName = $this->companyName ?: $this->contactPerson;
    }

    /**
     * Mengekstrak berbagai informasi dari satu teks
     */
    private function extractInfoFromText($text)
    {
        if (empty($text)) {
            return;
        }

        // Bersihkan ekstensi file jika ini nama file
        $text = preg_replace('/\.(xlsx|xls|csv)$/i', '', $text);

        // 1. Ekstrak Nama Perusahaan (PT/CV/PBF/UD)
        if (! $this->companyName && preg_match('/(PT|CV|PBF|UD)\.?[\s_:-]+([A-Za-z0-9\s_]+)/i', $text, $matches)) {
            $this->companyName = $this->cleanExtractedText($matches[0]);
        }

        // 2. Ekstrak Nama Sales / PIC (Hapus kata 'Distributor' agar tidak salah tangkap)
        if (! $this->contactPerson && preg_match('/(Sales|Nama Sales|Contact Person|PIC|Oleh)\.?[\s_:-]+([A-Za-z0-9\s_]+)/i', $text, $matches)) {
            // Hilangkan kata kuncinya agar yang tersimpan murni namanya saja
            $nameOnly = preg_replace('/(Sales|Nama Sales|Contact Person|PIC|Oleh)\.?[\s_:-]+/i', '', $matches[0]);

            // Jangan ambil teks yang kepanjangan (misal kalimat deskripsi)
            if (strlen($nameOnly) < 30) {
                $this->contactPerson = $this->cleanExtractedText($nameOnly);
            }
        }

        // 3. Ekstrak Nomor Telepon / HP / WA
        if (! $this->phone && preg_match('/(Telp|HP|No\.?\s*HP|WA|Phone|WhatsApp)\.?[\s_:-]*([0-9\-\+\s]{8,15})/i', $text, $matches)) {
            // Ambil hanya angkanya saja
            $this->phone = preg_replace('/[^0-9\+]/', '', $matches[2]);
        }
    }

    /**
     * Membersihkan teks hasil ekstraksi
     */
    private function cleanExtractedText($text)
    {
        $text = str_replace(['_', '-'], ' ', $text);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if (strlen($text) > 50) {
            $text = substr($text, 0, 50);
        }

        return strtoupper($text);
    }

    public function limit(): int
    {
        return 50;
    }
}

Dari hasil pengecekan tadi, hampir semua masalahnya berasal dari cara sistem **menyamakan nama obat** dan **menyimpan data**, bukan dari cara ngambil harga termurah. Ini saran gue, urut dari yang paling berdampak.

## 1. Bikin "kunci kanonik" obat, jangan cocokin pakai teks mentah

Sekarang kelihatannya nama dibersihkan dengan membuang semua tanda baca. Buktinya di hasil sortir:
- `Captopril 12,5 mg` jadi `CAPTOPRIL 125 MG` (dosisnya berubah 10x)
- `Clonazepam 0,5 mg` jadi `05 MG`
- `Acyclovir 5%` jadi `ACYCLOVIR 5`
- `Guaifenesin 100 mg/5 ml` jadi `100 MG5 ML`

Pecah nama jadi komponen terstruktur, lalu cocokkan berdasarkan kombinasi **zat + kekuatan + satuan + bentuk sediaan**:

```php
final class DrugNameParser
{
    public static function parse(string $raw, string $bentuk): array
    {
        $s = mb_strtolower(trim(preg_replace('/\s+/', ' ', $raw)));
        $s = str_replace(',', '.', $s);                 // 12,5 -> 12.5 (jangan dibuang!)

        // kekuatan: "500 mg", "5%", "100 mg/5 ml", "1000 iu", "0.1%"
        preg_match('/(\d+(?:\.\d+)?)\s*(mg|mcg|g|iu|%)(?:\s*\/\s*(\d+(?:\.\d+)?)\s*(ml))?/', $s, $m);

        $strength = $m ? rtrim(rtrim(number_format((float)$m[1], 3, '.', ''), '0'), '.') . $m[2]
                        . (isset($m[4]) ? "/{$m[3]}{$m[4]}" : '') : null;
        $name = trim(preg_replace('/\s+/', ' ', $m ? str_replace($m[0], '', $s) : $s));

        return [
            'name'      => $name,                        // "captopril"
            'strength'  => $strength,                    // "12.5mg"
            'form'      => mb_strtolower(trim($bentuk)), // "tablet" / "kaplet" / "kapsul"
            'canonical' => implode('|', [$name, $strength, mb_strtolower(trim($bentuk))]),
        ];
    }
}
```

Dengan ini `Ambroxol  30  mg`, `AMBROXOL 30 MG`, dan `ambroxol 30 mg` punya kunci yang sama. Sebaliknya, `Bisoprolol 5 mg` dan `10 mg` tidak akan pernah kecampur.

**Penting:** bentuk sediaan harus jadi bagian kunci. Paracetamol 500 mg Tablet dan Kaplet itu produk berbeda dengan harga berbeda.

## 2. Jangan pernah fuzzy-merge otomatis

Kalau tetap butuh fuzzy matching (misalnya untuk typo "Amoxicilin"), pakai cuma buat **saran**. Hasilnya masuk antrian review manual dan tidak langsung digabung. Obat itu tidak toleran salah: Diclofenac Potassium dan Sodium beda garam, Vitamin B1 dan B6 beda zat. Menyamakan keduanya menghasilkan 6 harga termurah yang salah tadi.

## 3. Skema database yang simpan asal-usul data

Sekarang supplier jadi "Unknown" karena informasinya hilang di tengah proses. Simpan `supplier_id` di setiap baris sejak import:

```php
Schema::create('drugs', function (Blueprint $t) {          // master obat kanonik
    $t->id();
    $t->string('canonical')->unique();
    $t->string('name'); $t->string('strength')->nullable(); $t->string('form');
});

Schema::create('supplier_prices', function (Blueprint $t) { // 1 baris = 1 harga dari 1 PT
    $t->id();
    $t->foreignId('supplier_id')->constrained();
    $t->foreignId('drug_id')->constrained();
    $t->foreignId('import_batch_id')->constrained();
    $t->string('raw_name');                                 // nama asli, untuk audit
    $t->unsignedInteger('price_unit')->nullable();
    $t->unsignedInteger('price_wholesale')->nullable();
    $t->timestamps();
    $t->unique(['supplier_id', 'drug_id', 'import_batch_id']); // cegah duplikat
});
```

Unique constraint itu yang mencegah masalah 1.696 baris "Sales ID" yang berulang-ulang. Import juga jadi idempotent, jadi upload file dua kali tidak menggandakan data.

## 4. Validasi saat import (jangan percaya isi Excel)

Baris sampah seperti "NAMA OBAT PRODUK" dan angka-angka dosis harusnya ditolak di pintu masuk:

- **Deteksi header dinamis.** Cari baris yang kolom A-nya `No` dan kolom B-nya `Nama Obat`. Posisinya beda-beda di tiap file (baris 6, 7, atau 8).
- **Tolak baris yang namanya sama dengan nama kolom header** (kasus Maju Terus baris 8).
- **Tolak nama yang cuma angka**, atau yang tidak punya huruf sama sekali.
- **Harga harus numerik dan > 0.** Harga grosir tidak boleh lebih besar dari harga satuan.
- **Whitelist bentuk sediaan** (Tablet, Kaplet, Kapsul, Krim, dst).
- **Deteksi outlier.** Kalau harga suatu PT lebih dari 3x atau kurang dari 0,3x median PT lain untuk obat yang sama, tandai "perlu dicek". Contoh: Acyclovir 5% di ISEKAI Rp 41.500 sementara yang lain Rp 8.500–12.000. Bisa jadi salah ketik, bisa jadi memang beda, tapi jangan diam-diam masuk.

Simpan baris yang gagal ke tabel `import_errors` lengkap dengan nomor baris dan alasannya, biar user bisa lihat.

## 5. Query termurah pakai SQL, bukan loop PHP

```php
$cheapest = DB::table('supplier_prices as sp')
    ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
    ->join('drugs as d', 'd.id', '=', 'sp.drug_id')
    ->where('sp.import_batch_id', $latestBatchPerSupplier) // pakai batch terbaru tiap PT
    ->selectRaw('d.id, d.canonical, s.name as supplier, sp.price_wholesale,
        ROW_NUMBER() OVER (PARTITION BY d.id ORDER BY sp.price_wholesale ASC, s.priority ASC) as rn')
    ->toBase();

$rows = DB::query()->fromSub($cheapest, 't')->where('rn', 1)->get();
```

Tiga hal di sini:
- **Tie-breaker harus eksplisit.** Di data lu, Farma Setia dan Maju Sehat sering harganya persis sama. Tanpa aturan, hasilnya acak. Tambah kolom `priority` di supplier, atau pilih supplier yang paling sering menang biar belanjanya terkonsolidasi.
- **Jadikan jenis harga (grosir/satuan) sebagai opsi**, jangan hardcode.
- **Simpan juga runner-up.** Berguna kalau supplier termurah lagi kosong.

## 6. Kelompokkan per supplier dengan mempertimbangkan minimum order

Kalau tujuan akhirnya "daftar belanja per PT", pertimbangkan:
- **Minimum order dan ongkir per supplier.** Kalau PT A cuma menang di 2 obat senilai Rp 50 ribu tapi minimum ordernya Rp 500 ribu, mending dipindah ke supplier lain yang selisih harganya kecil. Ini masalah optimasi sederhana, cukup pakai heuristik "kalau selisih < X%, gabungkan ke supplier utama".
- **Ketersediaan stok**, kalau ada kolomnya.
- **Tanggal berlaku pricelist.** Pricelist Farma Setia ada periodenya ("September 2026"), Maju Terus ada tanggal update. Harga yang sudah kedaluwarsa jangan ikut dibandingkan.

## 7. Bikin halaman "perlu review"

Buat UI kecil untuk:
- obat yang cuma ada di sebagian PT (harusnya bisa jadi sinyal typo)
- obat yang belum punya master kanonik
- alias manual, misalnya tabel `drug_aliases` (`raw_name` → `drug_id`) yang dipakai parser sebelum bikin master baru. Sekali user koreksi, sistem belajar.

## 8. Test dengan data nyata sebagai golden test

Pakai 5 file yang lu upload sebagai fixture. Assert-nya sederhana:

```php
public function test_import_five_suppliers(): void
{
    // ... import kelima file
    $this->assertSame(106, Drug::count());          // bukan 88, bukan 185
    $this->assertSame(530, SupplierPrice::count()); // 106 x 5
    $this->assertSame(0, ImportError::where('reason', 'like', '%angka%')->count() > 0 ? 0 : 0);
    $this->assertEquals(14400, $this->cheapest('bisoprolol|10mg|tablet')->price_wholesale);
    $this->assertEquals(4000,  $this->cheapest('vitamin b1|100mg|tablet')->price_wholesale);
}
```

Enam kasus salah tadi (Bisoprolol, Diclofenac, Meloxicam, Methylprednisolone, Salbutamol, Vitamin B1) bisa jadi test regresi permanen.

## 9. Hal teknis Laravel

- Pakai **Laravel Excel (maatwebsite/excel)** dengan `WithChunkReading` dan `WithHeadingRow`, dan jalankan lewat **queue** biar file besar tidak timeout.
- Bungkus per file dalam **DB transaction**. Kalau ada error fatal, semua batch di-rollback.
- Simpan `import_batches` (file, supplier, jumlah baris sukses/gagal, waktu) supaya tiap hasil sortir bisa ditelusuri ke file asalnya.
- Pakai `decimal`/integer rupiah, jangan float.

## Urutan pengerjaan yang gue sarankan

1. Perbaiki parser nama dan kunci kanonik (#1). Ini nyelesaiin 6 harga salah, 10 detail tercampur, dan sebagian besar produk hilang.
2. Validasi import (#4). Ini nyelesaiin 97 baris sampah.
3. Skema dengan unique constraint dan `supplier_id` (#3). Ini nyelesaiin "Unknown" dan duplikasi.
4. Sisanya (tie-breaker, review UI, minimum order) bisa menyusul.

Kalau mau, kirim kode Laravel yang sekarang (bagian import dan sortir-nya), nanti gue bantu review dan tunjukin persis bagian mana yang bikin nama kebuang tanda bacanya atau supplier jadi Unknown
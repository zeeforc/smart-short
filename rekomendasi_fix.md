Oke, gue sudah hitung ulang pakai asumsi baru ini: **pricelist sumber (ISEKAI, Maju Sehat, Tempra, dll) dianggap sudah termasuk PPN 12%.** Jadi untuk 43 item di SP Defecta, logikanya: `Harga Final = Harga Dasar × (1 − Diskon%)` — gak perlu dikali 1,12 lagi karena PPN-nya sudah nempel di harga sumber.

**Hasilnya: 39 dari 43 baris sudah benar.** Kolom PPN yang kosong (None) di file SP Defecta itu memang sudah tepat — bukan bug, karena harga dasarnya memang sudah termasuk PPN.

**Diskon 0% juga sudah benar** untuk semua 43 baris, karena supplier pemenangnya cuma PT ISEKAI, PT MAJU SEHAT, dan PT TEMPRA — bukan PT Maju Terus Farmasi (butuh qty ≥40 utk diskon 10%) atau PT. Solaris Multi Abadi (butuh qty ≥30 utk diskon 5%). Gue juga cek manual beberapa obat di daftar stok Solaris (Ambroxol, Amlodipine, Paracetamol, Captopril, Methylprednisolone) — harganya jauh lebih mahal daripada yang dipilih sistem, jadi walaupun Solaris ikut dibandingkan pun dia tetap kalah.

**4 baris masih salah** (bug yang sama dari laporan sebelumnya, belum diperbaiki):

| Obat | SP Defecta (salah) | Seharusnya (sesuai file sortir & pricelist asli) |
|---|---|---|
| Amlodipine 5 mg | PT ISEKAI, Rp 3.000 | PT Maju Sehat, Rp 5.600 (Rp 3.000 itu harga Amlodipine 10 mg) |
| Captopril 25 mg | PT Maju Sehat, Rp 4.000 | PT ISEKAI, Rp 4.000 (harga di pricelist Maju Sehat yang asli Rp 5.200, bukan Rp 4.000) |
| Methylprednisolone 8 mg | PT Maju Sehat, Rp 9.600 | PT ISEKAI, Rp 8.000 |
| Fexofenadine 180 mg | Rp 8.500 | Rp 10.500 (harga termurah asli dari PT Tempra) |

Ini saya cross-check langsung ke file `pricelist_PT_ISEKAI.xlsx` dan `PT_Maju_Sehat.xlsx` yang lu upload, bukan cuma dari file sortir — jadi ini bukan salah hitung pembanding, tapi sistemnya memang salah ambil baris pas proses sortir/tarik datanya.

**Satu catatan tambahan soal file Solaris:** harga di situ ditulis per **BOX** (isi puluhan sampai ribuan pcs/strip — lihat kolom terakhir, kebanyakan "30" atau "5"), bukan per tablet/strip kayak di 4 PT lainnya. Kalau nanti Solaris mau dimasukkan ke perbandingan sistem, harus dikonversi dulu ke harga per unit (`HNA+PPN ÷ isi per box`), kalau tidak, hasil perbandingannya bakal salah total karena satuannya beda.

Mau gue update file laporan (`Laporan_Cek_SP_Defecta.xlsx`) dengan hasil terbaru ini plus catatan soal Solaris, atau cukup ringkasan chat ini aja?



Berdasarkan 2 ronde pengecekan terakhir, bug-nya selalu berulang di pola yang sama. Ini saran konkret buat tiap masalah:

## 1. Bug utama: 4 item salah ambil harga/supplier — ini paling kritis

Pola errornya konsisten: **Amlodipine 5mg kebaca harga Amlodipine 10mg**, **Captopril & Methylprednisolone nyasar ke supplier yang bukan termurah**. Ini klasik bug "index shifting" atau join yang salah kunci.

Kemungkinan akar masalahnya di query Laravel lu:

```php
// RAWAN BUG kalau pakai LIKE atau cocokin nama parsial
$price = DrugPrice::where('name', 'LIKE', "%{$drugName}%")->first();
```

`LIKE "%Amlodipine%"` bisa nyangkut ke row manapun yang pertama ketemu di tabel (tergantung urutan insert), bukan yang namanya persis cocok. Ini jelasin kenapa Amlodipine 5mg bisa dapat harga Amlodipine 10mg — keduanya sama-sama "Amlodipine", query LIKE gak peduli angka di belakangnya.

**Perbaikan:** jangan pernah cocokkan obat pakai LIKE atau substring. Wajib pakai exact match ke kunci kanonik (nama + kekuatan + bentuk) yang sudah gue saranin di awal:

```php
$drug = Drug::where('canonical', DrugNameParser::parse($request->name, $bentuk)['canonical'])->first();
```

Terus bikin **test regresi permanen** 4 kasus ini biar gak kebocor lagi tiap kali ada perubahan kode:

```php
public function test_cheapest_price_matching_tidak_salah_sasaran(): void
{
    $this->assertEquals(5600, $this->cheapest('amlodipine|5mg|tablet')->harga);
    $this->assertEquals(4000, $this->cheapest('captopril|25mg|tablet')->harga);
    $this->assertEquals('PT ISEKAI', $this->cheapest('captopril|25mg|tablet')->supplier);
    $this->assertEquals(8000, $this->cheapest('methylprednisolone|8mg|tablet')->harga);
    $this->assertEquals(10500, $this->cheapest('fexofenadine|180mg|tablet')->harga);
}
```

## 2. Pisahkan field diskon & PPN dari "harga final" di level kalkulasi, bukan cuma tampilan

Sekarang strukturnya sudah lumayan bagus (Harga Dasar → Netto → PPN → Final), tapi biar gak ambigu lagi antar sesi kayak kemarin (apakah PPN perlu ditambah atau nggak), bikin eksplisit di skema:

```php
Schema::create('supplier_prices', function (Blueprint $t) {
    // ...
    $t->boolean('price_includes_ppn');   // flag per supplier/per pricelist, bukan asumsi global
    $t->unsignedInteger('price_raw');    // harga apa adanya dari file Excel/PDF
});
```

Field `pricelist_sudah_termasuk_ppn` yang lu punya di form "Edit Sales" itu udah tepat arahnya — tinggal dipastikan field itu **benar-benar dipakai di query penghitungan**, bukan cuma disimpan doang. Rumusnya:

```php
$hargaDasarBersihPPN = $supplier->pricelist_includes_ppn
    ? $hargaDasar
    : round($hargaDasar * 1.12);

$hargaFinal = round($hargaDasarBersihPPN * (1 - $diskon));
```

Dengan gini, kalau suatu saat ada supplier yang pricelist-nya belum termasuk PPN (beda dari asumsi sekarang), sistem tetap benar tanpa perlu ubah logika global.

## 3. Aturan diskon: validasi qty-nya, jangan cuma simpan

Diskon Maju Terus Farmasi & Solaris kemarin kebetulan gak kepake karena emang gak menang. Tapi kalau suatu saat mereka menang, pastikan sistem **benar-benar cek qty vs tier** saat itu juga, bukan nanti pas laporan dibuat manual. Simpan tier sebagai data terstruktur:

```php
Schema::create('supplier_discount_tiers', function (Blueprint $t) {
    $t->foreignId('supplier_id')->constrained();
    $t->unsignedInteger('min_qty');
    $t->decimal('discount_pct', 5, 2);
});
```

```php
$tier = SupplierDiscountTier::where('supplier_id', $supplier->id)
    ->where('min_qty', '<=', $qty)
    ->orderByDesc('min_qty')
    ->first();
$diskon = $tier?->discount_pct ?? 0;
```

Lalu bikin test: assert qty=39 ke Maju Terus Farmasi dapat diskon 0%, qty=40 dapat 10% — biar batas "lebih dari" vs "lebih dari sama dengan" gak salah pasang (`>` vs `>=` ini sering banget jadi off-by-one bug).

## 4. Normalisasi satuan harga — ini bakal jadi bom waktu

File Solaris itu harganya per **BOX** (isi bisa 5, 12, 30 pcs, bahkan ada yang isi 3.289!), sementara 4 PT lain per **unit/strip**. Kalau Solaris nanti dimasukkan ke perbandingan tanpa konversi, sistem bakal mikir Solaris "lebih mahal" padahal sebenarnya lebih murah per unit, atau sebaliknya — fatal banget buat pharmacy.

Tambahkan field wajib saat import:

```php
Schema::table('supplier_prices', function (Blueprint $t) {
    $t->unsignedInteger('isi_per_kemasan')->default(1); // isi per box/pack
    $t->unsignedInteger('harga_per_unit_calc')->virtualAs('price_raw / isi_per_kemasan');
});
```

Dan **validasi wajib isi "isi per kemasan" saat upload pricelist baru**, jangan default ke 1 secara diam-diam — itu sumber salah hitung paling halus karena gak keliatan errornya, cuma hasil akhirnya salah.

## 5. Parser PDF butuh perhatian ekstra

Solaris datang dalam bentuk PDF, bukan Excel. Format ini jauh lebih rawan salah parse (kolom nyambung tanpa pemisah jelas kayak "SAMPHARINDOBOX" — nama perusahaan nempel ke satuan). Saran:
- Jangan andalkan regex split spasi doang buat PDF kayak gini. Perusahaan dan satuan sering nempel karena kolom PDF-nya sempit.
- Siapkan daftar whitelist nama manufaktur & satuan (BOX, FLS, TUBE, BTL, dst) buat bantu misahin token yang nempel.
- Kasih preview hasil parsing ke user sebelum di-commit ke database (halaman "Status Antrean" yang udah lu punya bisa dipakai buat ini) — biar kalau parsernya salah motong, ketahuan sebelum masuk ke hasil sortir.

## Prioritas pengerjaan

| Urutan | Yang dikerjain | Kenapa |
|---|---|---|
| 1 | Ganti matching LIKE/substring → exact canonical key | Nyebabin 4 bug yang paling parah dan berulang |
| 2 | Test regresi 4 kasus + tier diskon | Supaya gak kebocor lagi diam-diam |
| 3 | Field `isi_per_kemasan` wajib saat import | Sebelum Solaris aktif dipakai sistem |
| 4 | Preview hasil parsing PDF sebelum commit | Khusus buat sumber data non-Excel |

Kalau lu kirim kode service/controller yang nangani proses sortir & hitung harga final sekarang, gue bisa tunjukin baris persis mana yang kemungkinan jadi sumber bug matching-nya.
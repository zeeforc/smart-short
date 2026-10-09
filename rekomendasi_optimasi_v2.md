Analisis Auto-Sortir Price List: Hanya 16 dari 463 Obat Terdeteksi
Untuk: Tim Developer (Laravel + Filament) · Dibuat: 9 Oktober 2026
1. Ringkasan
Daftar restock 7 Oktober berisi 463 baris (459 nama unik). Sistem sortir otomatis hanya mengenali 16 obat, padahal sebagian besar obat tersebut ada di price list.
Akar masalahnya bukan di data harga, tapi di pencocokan nama. Nama di daftar restock adalah nama internal singkat (contoh: ACIFAR 400 MG TAB), sedangkan tiap supplier menulis nama dengan format sendiri-sendiri. Sistem tampaknya mencocokkan nama secara hampir persis (exact atau mendekati), sehingga hampir semua baris gagal. Tidak ada tabel master produk atau kamus alias yang menjembatani perbedaan itu.
Catatan: kami tidak punya akses ke kode sistem. Analisis ini disimpulkan dari isi 7 file yang diunggah dan simulasi pencocokan. Angka 16 tidak bisa direproduksi persis, tapi simulasi kami berada di rentang yang sama (lihat bagian 3).
2. File yang dianalisis
Sumber
Format
Jumlah item
Catatan struktur
7_OKTOBER.xlsx
Excel
463 baris
Daftar kebutuhan: nama + qty, tanpa header
DEAL_26-09-2026.xlsx
Excel
5.965
Kolom: kode, nama, HNA+PPN, disc 1, disc 2, stok
DEAL_28_09.xlsx
Excel
2.296
Kolom: nama, HNA, qty<10, disc
PRICELIST_30_SEPTEMBER_2026.xlsx
Excel
1.100
Header 6 baris + merged cell; nama dipadding spasi
PL_Harga_Jual_ABC_PT_SDL (PDF)
PDF
± 880
Harga A/B/C dengan diskon bertingkat
Laporan_Stok_Per_Batch1 (PDF, PT Solaris)
PDF
± 510
Format nama berbeda sendiri
Stok_Obat_SDL_30_Sept (PDF, 141 halaman)
PDF
± 800 nama, banyak baris batch
Per batch, gudang KARTONAN dan ECER
Jumlah item dari PDF adalah hasil parsing kami sehingga bisa bergeser sedikit.
3. Hasil simulasi pencocokan
Kami mencocokkan 459 nama unik di daftar restock ke gabungan semua sumber dengan beberapa strategi, dari yang paling ketat:
Strategi
Nama yang ketemu
Exact persis (tanpa normalisasi)
6
Exact setelah trim, huruf besar, rapikan spasi
28
Semua kata di request ada di nama supplier
155
+ kamus sinonim + buang kata noise
310 (± 68%)
+ fuzzy similarity ≥ 0,8 (hanya saran)
+ 78 kandidat
Tetap tidak ketemu
± 71
Kesimpulan: sistem Anda yang menemukan 16 berada di zona exact-match. Dengan normalisasi dan kamus sinonim saja, cakupan naik belasan kali lipat tanpa mengubah satu pun file sumber.
Perhatian: 78 kandidat fuzzy tidak boleh diterima otomatis. Beberapa jelas salah, misalnya AMOXSAN SYR 60 ML cocok ke AMOXSAN 500MG CAPS, dan FASIDOL FORTE 650 MG TAB cocok ke FASIDOL FORTE SYR 60 ML.
4. Penyebab (diurutkan dari dampak terbesar)
4.1 Tidak ada master produk dan kamus alias
Produk yang sama ditulis berbeda di tiap sumber. Contoh ACIFAR 400 MG TAB:
Sumber
Penulisan
Daftar restock
ACIFAR 400 MG TAB
DEAL 26
ACIFAR 400MG KAP'IFARS/100
DEAL 28
ACIFAR 400MG TAB  NO RETUR
PL SDL
ACIFAR 400MG KAP 30S
Solaris
400 mg - ACIFAR 3 x 10 kapl.
Tidak ada satu pun yang sama persis. Tanpa tabel yang menyatakan "kelima nama ini adalah produk yang sama", pencocokan berbasis string akan terus gagal.
4.2 Noise khas tiap sumber
• DEAL 26: kode pabrik dalam tanda kutip dan jumlah per karton menempel di nama, contoh AMLODIPINE 5MG 100'S'HEX'/192. Sebanyak 4.329 dari 5.965 nama mengandung tanda kutip di tengah nama. Dua nama diawali tanda kutip tunggal.
• DEAL 28: akhiran status retur dengan banyak ejaan: NORET, NORETUR, NO RETUR, NR, NRT, /NORETUR. Ada 1.568 dari 2.296 nama yang membawanya. Baris ke-2 berisi ED.D dan bukan data.
• PRICELIST 30 SEPT: nama dipadding spasi sampai sekitar 50 karakter, dan pabrik ditulis setelah tanda strip (ACARBOSE TAB 100 MG - DEXA). Header memakai merged cell.
• Solaris (PDF): urutan terbalik (kekuatan dulu), huruf dipisah spasi (A M L O, C E F I X I M E, A C I F A R), awalan -  dan Pot / 100 -, serta nama pabrik menempel ke satuan (DARYA VARIABOX, SAMPHARINDOBOX) sehingga pemisahan kolom berbasis spasi gagal.
• PL SDL (PDF): kode pabrik singkat tidak seragam (HJ, DM, FM, SE, NV, DX). Singkatan pabrik di daftar restock (HJ, DEXA, NOVA, KF, IPHA) tidak sama dengan singkatan di sumber.
4.3 Variasi penulisan bentuk sediaan, kekuatan, dan ukuran
Konsep
Variasi yang ditemukan
Tablet
TAB, TABLET
Kapsul / kaplet
KAP, KAPL, KAPLET, KPT, KAPS, KAPSUL, CAPS
Sirup
SYR, SYRUP, SIRUP, SUSP, SUSPENSI
Krim
CREAM, CR, KRIM
Gram
5 GR, 5GR, 5GRAM, 5 G
Kekuatan
400 MG vs 400MG
Tetes
TETES, DROP, DROPS, TTS
Nama zat
METILPREDNISOLON / METHYLPREDNISOLONE / METHYL; DESOXIMETHASONE / DESOXIMETASONE; BETAMETHASONE / BETAMETASON
Selain itu, request sering tidak menyebut bentuk sediaan yang sama dengan supplier. Misalnya request ACIFAR 400 MG TAB, supplier menulis KAP.
4.4 Nama request terlalu umum sehingga ambigu
Terdapat 27 baris request yang menghasilkan lebih dari 8 kandidat, misalnya CAVIPLEX KAPL (23 kandidat), BODREX TABLET (22), INSTO 7,5 ML (14). Jika logika sistem hanya menerima kecocokan tunggal, baris ambigu akan ikut terbuang. Seharusnya baris ambigu masuk antrean review, bukan hilang.
4.5 Typo dan karakter tak terlihat di daftar request
Di daftar request
Seharusnya
ERLMAYCETIN
ERLAMYCETIN
ACETYLSISTEIN
ACETYLCYSTEIN
AKURAT TEST KEJAMILAN
KEHAMILAN
CESSA BABY HAPY NOSE
HAPPY NOSE
AMPICILIN
AMPICILLIN
METILPREDNISOLON
METHYLPREDNISOLONE
DESOXIMETHASONE
DESOXIMETASONE
PHARPROS
PHAPROS
Ada juga spasi di akhir nama ('AKURAT TEST KEJAMILAN '), spasi ganda (HOT IN CREAM  ORIG), dan non-breaking space (SIMVASTATIN 10\u00a0\u00a0TAB HJ, karakter \xa0). Karakter ini membuat perbandingan string gagal walau terlihat sama di Excel.
4.6 Kolom qty tidak bersih
• LANADEXON 0,5 MG TAB qty = '5 B0X' (teks, dan huruf O ditulis angka nol)
• LION HEAD 31 GR qty = '5 POT' (teks)
• VITAMIN B COMPLES TABLET NOVA qty = 7915 (hampir pasti salah ketik, ini kemungkinan kode atau salah input)
• Empat nama muncul dua kali dengan qty berbeda: DISPOSABLE NEEDLE 23 G ONEMED, NOVAMOX 500 MG TAB, TERA-F KAPL, VENTASAL INHALASI. Jika nama dipakai sebagai key unik, salah satu baris tertimpa atau ditolak.
Jika kolom qty dicast ke integer secara ketat, baris dengan teks bisa melempar error atau di-skip diam-diam.
4.7 Sebagian item memang tidak ada di price list (bukan bug)
Sekitar 71 baris tidak ketemu sama sekali. Sebagian besar non-obat atau alkes: CELANA KHITAN, HANDSCOON, TERMOMETER, PIPET DROP, ARM SLING, STIK ASAM URAT, STIK KOLESTEROL, MADU TJ, IKAN GABUS, KASA, dll. Ini wajar, tapi sistem harus melaporkannya sebagai "tidak ada di supplier manapun" dan bukan diam.
4.8 Struktur file sumber yang menyulitkan parsing
• Kolom stok DEAL 26 berisi teks BANYAK pada 843 baris, sementara sisanya angka. Cast numerik langsung akan gagal atau menjadi 0, sehingga item terbaca "stok kosong".
• PDF stok SDL berisi satu baris per batch dan per gudang (KARTONAN dan ECER), jadi satu obat muncul banyak kali. Stok perlu dijumlahkan.
• Angka bermasalah di PDF: stok negatif (-151), pemisah ribuan koma (3,146), harga salah format (Rp45.000 untuk HNA, padahal sisanya Rp45,000), nilai tanpa format (27000).
• Catatan bebas di tengah tabel yang tidak boleh dianggap item: Harga A = 24.975 klo beli 20 tb keatas, Beli 20 box bonus 1 box, Harga 118.000 klo beli diatas 10 box.
• Baris judul pabrik (KALBE, SOLAS, MEPRO PREKURSOR) bercampur dengan baris produk.
4.9 Basis harga tidak seragam (risiko "harga terbaik" yang salah)
Bahkan setelah nama cocok, harga belum bisa dibandingkan langsung:
Sumber
Basis harga
DEAL 26
HNA + PPN, ada Disc 1 dan Disc 2 (kemungkinan berlapis, mohon dikonfirmasi)
DEAL 28
HNA (header tidak menyebut PPN), satu kolom disc, ada kolom qty<10
PRICELIST 30 SEPT
HNA, disc, dan Harga Jadi = HNA × (1 − disc) × 1,11 (kami cek, rumus ini benar)
PL SDL
HNA sebelum PPN, tiga tingkat Harga A/B/C dengan diskon berbeda, sebagian bersyarat qty
Solaris
HNA+PPN dan kolom % tanpa penjelasan
Jika ada yang dibandingkan sebelum PPN dan ada yang sesudah, atau stok 0 ikut dihitung, supplier "termurah" bisa keliru.
5. Solusi
5.1 Prinsip
1. Jangan cocokkan string, cocokkan atribut produk (nama dagang atau zat, kekuatan, bentuk, kemasan, pabrik).
2. Simpan hasil koreksi manusia sebagai alias permanen supaya sistem makin pintar tiap bulan.
3. Tidak ada baris yang hilang diam-diam. Setiap baris punya status: matched, ambiguous, atau unmatched.
4. Pisahkan tahap: import → normalisasi → matching → normalisasi harga → pilih terbaik.
5.2 Skema database (Laravel migration, disederhanakan)
// products: master produk milik Anda (sekali bikin, terus dirawat)
Schema::create('products', function (Blueprint $t) {
    $t->id();
    $t->string('canonical_name');          // ACIFAR 400MG KAP 30S
    $t->string('brand')->nullable();       // ACIFAR
    $t->string('active_ingredient')->nullable();
    $t->string('strength')->nullable();    // 400MG
    $t->string('form')->nullable();        // TAB|KAP|SYR|CR|OINT|DROP|INJ
    $t->string('pack')->nullable();        // 30S, 60ML, 5G
    $t->string('manufacturer')->nullable();
    $t->timestamps();
});

// product_aliases: kamus penghubung nama mentah -> produk
Schema::create('product_aliases', function (Blueprint $t) {
    $t->id();
    $t->foreignId('product_id')->constrained();
    $t->foreignId('supplier_id')->nullable();   // null = berlaku untuk semua
    $t->string('alias_normalized')->index();
    $t->string('alias_raw');
    $t->decimal('confidence', 4, 2)->default(1);
    $t->boolean('verified')->default(false);
    $t->timestamps();
    $t->unique(['supplier_id', 'alias_normalized']);
});

// supplier_items: hasil import semua price list, satu baris per item per import
Schema::create('supplier_items', function (Blueprint $t) {
    $t->id();
    $t->foreignId('import_id');
    $t->foreignId('supplier_id');
    $t->foreignId('product_id')->nullable()->index();
    $t->string('raw_name');
    $t->string('normalized_name')->index();
    $t->json('attributes')->nullable();
    $t->decimal('hna', 14, 2);
    $t->boolean('hna_includes_vat');
    $t->decimal('disc1', 5, 2)->default(0);
    $t->decimal('disc2', 5, 2)->default(0);
    $t->json('price_tiers')->nullable();       // Harga A/B/C, syarat qty
    $t->string('stock_status');                // numeric|plenty|zero|unknown
    $t->integer('stock_qty')->nullable();
    $t->string('unit')->nullable();
    $t->timestamps();
});

// restock_lines: baris dari daftar kebutuhan
Schema::create('restock_lines', function (Blueprint $t) {
    $t->id();
    $t->foreignId('batch_id');
    $t->string('raw_name');
    $t->string('normalized_name');
    $t->string('qty_raw')->nullable();
    $t->unsignedInteger('qty')->nullable();
    $t->foreignId('product_id')->nullable();
    $t->string('match_status');                // matched|ambiguous|unmatched|not_stocked
    $t->decimal('match_score', 4, 2)->nullable();
    $t->json('warnings')->nullable();
    $t->timestamps();
});
5.3 Normalizer (satu class, dipakai di SEMUA sumber dan request)
final class NameNormalizer
{
    private const SYN = [
        'TABLET'=>'TAB','KAPLET'=>'KAP','KAPL'=>'KAP','KPT'=>'KAP','KAPS'=>'KAP',
        'KAPSUL'=>'KAP','CAPS'=>'KAP','CAP'=>'KAP',
        'SYRUP'=>'SYR','SIRUP'=>'SYR','SUSPENSI'=>'SYR','SUSP'=>'SYR',
        'CREAM'=>'CR','KRIM'=>'CR','SALEP'=>'OINT',
        'INJEKSI'=>'INJ','TETES'=>'DROP','DROPS'=>'DROP','TTS'=>'DROP',
        'GRAM'=>'GR','GM'=>'GR','G'=>'GR',
        'METILPREDNISOLON'=>'METHYLPREDNISOLONE','METHYLPREDNISOLON'=>'METHYLPREDNISOLONE',
        'ACETYLSISTEIN'=>'ACETYLCYSTEINE','ACETYLCYSTEIN'=>'ACETYLCYSTEINE',
    ];
    private const NOISE = ['NORET','NORETUR','NRT','NR','RETUR','NO','NEW'];

    public function tokens(string $raw): array
    {
        $s = str_replace("\u{00A0}", ' ', $raw);          // NBSP
        $s = mb_strtoupper(trim($s));
        $s = preg_replace('/(\d)\s*(MG|MCG|GR|GM|ML|G|IU|CC|%)\b/', '$1$2', $s); // 400 MG -> 400MG
        $s = preg_replace('/(\d),(\d)/', '$1.$2', $s);     // 7,5 -> 7.5
        $s = preg_replace('/\/\s*\d+\s*$/', '', $s);        // buang "/144" di ujung (DEAL 26)
        $s = preg_replace('/[^A-Z0-9%. ]+/', ' ', $s);     // buang kutip, strip, dsb
        $out = [];
        foreach (preg_split('/\s+/', trim($s)) as $t) {
            $t = self::SYN[$t] ?? $t;
            if (!in_array($t, self::NOISE, true)) $out[] = $t;
        }
        return $out;
    }
}
Sinonim dan noise sebaiknya disimpan di tabel yang bisa diedit lewat Filament, bukan di-hardcode, supaya tim non-developer bisa menambah.
Khusus Solaris, tambahkan pra-proses: gabungkan huruf yang dipisah spasi (A M L O menjadi AMLO), buang awalan -  dan Pot / 100 -, dan pindahkan kekuatan di depan nama ke belakang.
5.4 Matcher berlapis
Urutan dari paling yakin ke paling lemah:
1. Alias terverifikasi: cari alias_normalized (untuk supplier terkait, lalu global). Jika ada, selesai, status matched.
2. Atribut persis: ekstrak brand, kekuatan, bentuk, kemasan, lalu cocokkan. Kekuatan dan bentuk wajib sama bila disebut di request (ini mencegah ALERZIN TAB cocok ke LERZIN SIRUP).
3. Skor token: semua token inti request harus ada di nama supplier. Hitung skor, simpan kandidat teratas.
4. Fuzzy (trigram atau Levenshtein) hanya menghasilkan saran, tidak pernah otomatis diterima. Gunakan Laravel Scout + Meilisearch atau MySQL FULLTEXT, atau pg_trgm bila Postgres.
5. Keputusan: 1 kandidat dengan skor tinggi menjadi matched; lebih dari 1 kandidat sama kuat menjadi ambiguous; tidak ada kandidat menjadi unmatched; tidak ada di sumber manapun dan terdeteksi non-obat menjadi not_stocked.
Ketika ambiguous, tetap tampilkan semua kandidat beserta harga supaya pembeli bisa memilih. Jangan dibuang.
5.5 Importer per supplier (pola adapter)
interface SupplierImporter
{
    /** @return iterable<SupplierItemDTO> */
    public function parse(string $path): iterable;
}
// Deal26Importer, Deal28Importer, LabMedikaImporter (xlsx via maatwebsite/excel)
// SdlPdfImporter, SolarisPdfImporter, SdlStokPdfImporter
Setiap importer wajib:
• Mengabaikan baris non-data (header, ED.D, judul pabrik, catatan) dan menyimpan catatan sebagai teks syarat untuk ditampilkan.
• Mengubah stok: angka tetap angka, BANYAK menjadi stock_status = plenty, 0 menjadi zero.
• Menjumlahkan stok per produk untuk PDF per batch (KARTONAN + ECER).
• Parsing angka lewat satu helper yang mengerti Rp45.000, Rp45,000, 3,146, 27000, dan negatif.
• Membuat laporan import: jumlah baris dibaca, dipakai, di-skip beserta alasannya.
Untuk PDF, jalankan pdftotext -layout (atau smalot/pdfparser) lalu parse per baris dengan regex per supplier. Cara yang paling stabil: minta supplier mengirim Excel. PDF Solaris misalnya sudah kehilangan batas kolom.
5.6 Normalisasi harga sebelum memilih yang terbaik
Buat satu fungsi yang mengubah semua sumber ke harga bersih per unit jual, sudah termasuk PPN:
$base = $item->hna_includes_vat ? $item->hna : $item->hna * 1.11;
$net  = $base * (1 - $item->disc1/100) * (1 - $item->disc2/100); // konfirmasi: berlapis atau dijumlah?
Lalu aturan pemilihan:
• Buang item stok zero. Item plenty dianggap tersedia.
• Untuk harga bertingkat (PL SDL Harga A/B/C, syarat 20 tube ke atas), pilih tingkat berdasarkan qty request.
• Tampilkan syarat dan bonus (beli 20 bonus 1) di samping harga, karena bonus bisa membuat supplier lebih mahal terlihat lebih murah.
• Jika tiap tingkat berbeda retur (NORET) atau ED, tampilkan sebagai kolom, jangan dijadikan filter diam-diam.
Pertanyaan yang perlu dikonfirmasi bisnis: apakah Disc 1 dan Disc 2 di DEAL 26 berlapis, dan apakah HNA di DEAL 28 sudah termasuk PPN.
5.7 Pembersihan input daftar request
Saat upload 7_OKTOBER.xlsx:
• Trim, ganti NBSP, rapikan spasi ganda.
• Parse qty dengan regex angka. 5 B0X menjadi 5 disertai warning "qty mengandung teks". Qty di atas ambang (misalnya > 500) ditandai "perlu dicek" (kasus 7915).
• Nama duplikat: gabungkan atau tandai, jangan menimpa.
• Tampilkan ringkasan sebelum diproses: berapa baris valid, warning, dan duplikat.
5.8 Halaman review di Filament
Ini bagian terpenting karena membuat sistem belajar:
• Resource Hasil Matching dengan filter status (unmatched, ambiguous) dan kolom: nama request, qty, kandidat teratas beserta skor.
• Action "Pilih produk ini": menyimpan baris ke product_aliases dengan verified = true. Bulan depan nama yang sama langsung matched.
• Action "Tandai bukan stok supplier" untuk alkes dan non-obat.
• Action "Buat produk baru" bila produk memang belum ada di master.
• Kartu ringkasan di atas tabel: total baris, matched, ambiguous, unmatched, not_stocked.
Bulk-seed awal: jalankan matcher pada daftar bulan ini, ambil hasil yang skornya tinggi, lalu verifikasi sekali. Setelahnya cakupan akan naik tiap bulan.
6. Rencana implementasi bertahap
Tahap
Pekerjaan
Hasil yang diharapkan
1 (cepat)
NameNormalizer + sinonim + buang noise, dipakai ke request dan semua sumber
Cakupan kandidat naik dari 16 ke sekitar 300 dari 459
2
Tabel product_aliases + halaman review Filament
Cakupan mendekati 90%+ setelah 1–2 siklus verifikasi
3
Importer per supplier + laporan import + normalisasi harga
Hasil "harga terbaik" bisa dipercaya
4
Master produk lengkap, atribut terstruktur, search engine (Scout)
Matching stabil untuk bulan-bulan berikutnya
Angka 300 berasal dari simulasi kami sebagai jumlah baris yang punya minimal satu kandidat, belum tentu semuanya benar. Akurasi harus diukur lewat review.
7. Pengujian
• Jadikan 463 baris 7_OKTOBER.xlsx sebagai golden set: setelah diverifikasi manual, simpan sebagai fixture dan tes regresi setiap kali aturan normalisasi diubah.
• Unit test untuk NameNormalizer memakai contoh di bagian 4.1 sampai 4.5 (misalnya semua variasi ACIFAR 400MG harus menghasilkan token inti yang sama).
• Test parser angka: Rp45.000, 3,146, -151, 27000, 5 B0X.
• Test bahwa ALERZIN TAB tidak pernah otomatis cocok ke LERZIN SIRUP.
8. Daftar periksa untuk developer
[ ] Satu NameNormalizer dipakai di request dan semua importer
[ ] Sinonim dan noise disimpan di database, bisa diedit di Filament
[ ] Tabel products dan product_aliases ada, alias terverifikasi dicek lebih dulu
[ ] Fuzzy hanya jadi saran, bukan auto-accept
[ ] Status per baris: matched / ambiguous / unmatched / not_stocked, tidak ada yang hilang diam-diam
[ ] Stok BANYAK, negatif, dan multi-batch ditangani
[ ] Harga dinormalisasi (PPN, diskon, tingkat harga, syarat qty) sebelum dibandingkan
[ ] Laporan import menampilkan baris yang di-skip beserta alasannya
[ ] Validasi qty dan duplikat saat upload daftar request
[ ] Halaman review Filament dengan aksi simpan-sebagai-alias
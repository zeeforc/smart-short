# Panduan Penggunaan Smart Short (Defecta & Sortir Harga Pemenang)

Dokumen ini adalah panduan resmi langkah demi langkah operasional aplikasi **Smart Short** untuk apotek: mulai dari pengaturan supplier, upload daftar harga (pricelist), impor defecta belanja, hingga mendapatkan Surat Pesanan (SP) otomatis dengan harga termurah.

---

## Ringkasan Alur Kerja

```
[1. Master Supplier & Pajak] 
         ↓
[2. Upload Pricelist Distributor]
         ↓
[3. Upload Defecta / Rencana Belanja]
         ↓
[4. Kalkulasi Cerdas (Auto-Match & Alert)]
         ↓ (Jika ada 1-2 obat belum cocok: Cocokkan 1-Klik)
[5. Unduh Surat Pesanan (Excel)]
```

---

## Langkah 1: Pengaturan Master Distributor & Diskon
**Menu: Master Data → Suppliers**

Langkah ini dilakukan sekali di awal, atau saat ada distributor baru / ada perubahan kebijakan diskon.

1. Buka menu **Suppliers**, pilih distributor yang ingin diatur, lalu klik **Edit**.
2. **Atur Status Pajak (PPN 12%):**
   * **Centang "Harga Termasuk PPN":** Jika harga di file pricelist distributor sudah harga bersih include pajak (Contoh: PT ISEKAI, PT Tempra, dll).
   * **Jangan Centang:** Jika harga di pricelist masih harga dasar / HNA sebelum pajak. Sistem otomatis menambahkan PPN 12% saat membandingkan harga.
3. **Atur Diskon Bertingkat (Tiers) - Opsional:**
   * Di bagian bawah, masukkan aturan minimal order (Min QTY) dan persentase diskon.
   * *Contoh:* Min QTY `30` diskon `5%`, Min QTY `60` diskon `10%`. Sistem otomatis memotong diskon jika pembelian di defecta mencapai batas tersebut.
4. **Smart Mapping Kolom Excel (Opsional):**
   * Sistem **sudah memiliki deteksi otomatis bawaan** untuk kolom nama barang, satuan, harga, dan diskon.
   * Lo **hanya perlu mengisi bagian ini jika** file Excel supplier tersebut punya nama kolom yang sangat aneh (misal: header harga tertulis `H_NET_DIST_2026`).

---

## Langkah 2: Upload Pricelist Distributor
**Menu: Master Data → Upload Batch**

Lakukan langkah ini setiap kali sales distributor mengirimkan daftar harga baru (misal update bulanan).

1. Buka menu **Upload Batch**, klik tombol **Upload File Pricelist**.
2. Pilih distributor dan unggah file:
   * Format yang didukung: Excel (`.xlsx`, `.xls`), `.csv`, atau PDF.
   * Jika sales memberikan diskon umum langsung untuk seluruh file tersebut (misal diskon promo 3%), isi pada kolom Diskon Global.
3. Klik **Simpan**.
4. Sistem otomatis memproses baris produk:
   * Membersihkan noise dan istilah non-obat seperti `NORET`, `STRIP`, spasi ganda, tanda baca.
   * Menstandarisasi dosis dan kekuatan obat (misal `500 MG` menjadi `500mg`).
   * Menyimpan riwayat harga ke database.

---

## Langkah 3: Masukkan Daftar Belanja (Defecta)
**Menu: Transaksi → Smart Defecta (PO)**

Halaman ini berfungsi sebagai keranjang belanja terpusat apotek. Data tersimpan di server dan tidak akan hilang saat browser ditutup.

1. Buka menu **Smart Defecta (PO)**.
2. Masukkan obat yang ingin dipesan:
   * **Cara Cepat (Import Excel):** Klik tombol hijau **Import Data Pesanan (Excel)** di pojok kanan atas, lalu pilih file Excel defecta dari apotek.
     * *Catatan Pintar:* File defecta otomatis dibersihkan oleh sistem. Jika ada obat yang tertulis 2 baris terpisah, jumlahnya otomatis dijumlahkan. Format kotor seperti `5 B0X` otomatis dirapikan menjadi angka `5`.
   * **Cara Tambah Satuan:** Klik tombol **Tambah Obat Manual** untuk menambahkan obat satu per satu.
3. Periksa tabel antrean belanja di bagian atas halaman.

---

## Langkah 4: Jalankan Kalkulasi Cerdas
**Tombol: "Kalkulasi Pemenang"**

1. Klik tombol biru **Kalkulasi Pemenang** di atas tabel.
2. Sistem menjalankan **Multi-Layer Smart Matcher**:
   * **Pengecekan Kamus Alias:** Jika obat pernah dicocokkan sebelumnya, langsung cocok 100%.
   * **Pencocokan Urutan Kata:** Obat seperti `SANMOL FORTE SYR` otomatis cocok dengan `SANMOL SYR FORTE`.
   * **Toleransi Typo:** Ejaan salah seperti `AMOXICILIN 500MG` otomatis cocok ke `AMOXICILLIN 500MG`.
   * **Pengaman Medis:** Dosis `5mg` dijamin tidak akan tertukar dengan `10mg`, dan sirup tidak akan tertukar dengan tablet.
   * **Auto-Match & Auto-Save:** Obat dengan kemiripan $\ge 85\%$ otomatis dianggap cocok dan otomatis disimpan ke kamus alias.
   * **Penghitungan Harga Termurah:** Membandingkan seluruh distributor, memotong diskon QTY, menambah PPN jika belum include, lalu menetapkan pemenang.

---

## Langkah 5: Cek Alert & Cocokkan Obat yang Belum Cocok
Jika ada obat yang belum pernah ada di pricelist distributor manapun atau namanya ambigu, sistem akan menampilkan **Banner Peringatan (Alert Box)** berwarna oranye di atas kartu hasil:

```
⚠️ Perlu Perhatian: 2 Obat Belum Terhubung ke Pricelist
1. ALERZIN SIRUP (10 item) — 💡 Rekomendasi terdekat: LERZIN SIRUP 60ML (Akurasi: 82%)  [Cocokkan Manual]
2. ALKES SPUIT 3CC (5 item) — Tidak ditemukan di pricelist supplier manapun             [Cocokkan Manual]
```

### Cara Mencocokkan (1-Klik atau Sekaligus):
1. Klik tombol **Cocokkan Manual** pada baris obat tersebut, atau klik **Cocokkan Sekaligus** untuk memproses banyak obat dalam satu modal interaktif.
2. Modal akan muncul dan **rekomendasi obat terdekat sudah otomatis terpilih**.
3. Klik tombol **Simpan**.
4. Sistem otomatis menyimpan ke tabel **Alias & Cocokkan Obat**, lalu langsung mengkalkulasi ulang pemenang.
5. **Ingat Selamanya:** Bulan depan saat obat tersebut muncul di defecta, sistem sudah mengingatnya dan langsung cocok secara otomatis!

---

## Langkah 6: Unduh Surat Pesanan (Excel)
**Tombol: "Download Surat Pesanan (Excel)"**

1. Setelah semua hasil kalkulasi sesuai, klik tombol hijau **Download Surat Pesanan (Excel)** di sebelah kanan atas hasil rekomendasi.
2. File Excel otomatis diunduh dengan struktur siap pakai:
   * **Tergrup rapi per Distributor Pemenang:** Memudahkan staf apotek memisahkan pesanan untuk masing-masing sales.
   * **Rincian Transparan:** Menampilkan QTY, Harga Dasar, Diskon (%), Harga Netto, PPN 12%, Harga Final per Satuan, dan Total Pembelian.
   * **Item Tanpa Pricelist:** Jika ada obat yang memang tidak dijual oleh distributor manapun, obat tersebut tetap tercantum di bagian bawah dengan penanda khusus agar apoteker tahu item tersebut harus dicari ke apotek/PBF rekanan lain.

---

## Tips & Tanya Jawab (FAQ)

### Q: Apakah saya harus menyetel Smart Mapping di setiap supplier satu per satu?
**Tidak.** Sistem sudah dibekali daftar kata kunci sinonim umum (nama, obat, produk, hna, harga, gross, net, disc, dll). Anda hanya perlu membuka pengaturan mapping jika ada supplier yang format Excel-nya aneh dan gagal terbaca otomatis.

### Q: Bagaimana cara melihat atau mengedit daftar obat yang sudah pernah dicocokkan?
Buka menu **Master Data → Alias & Cocokkan Obat**. Di sana Anda bisa melihat seluruh daftar nama asli di defecta beserta produk master pasangannya, dan bisa dihapus atau diubah kapan saja.

### Q: Mengapa distributor A yang diskonnya lebih besar tidak menang melawan distributor B?
Periksa status PPN distributor: jika distributor A harganya belum termasuk PPN (kena tambahan 12%), harga akhirnya bisa jadi lebih mahal dibanding distributor B yang harganya sudah include PPN meskipun diskonnya lebih kecil. Sistem selalu membandingkan **harga final netto riil yang harus dibayar apotek**.

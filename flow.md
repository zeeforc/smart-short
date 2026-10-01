# Panduan Alur Kerja Smart Short (Defecta & Surat Pesanan)

Dokumen ini menjelaskan alur operasional sistem pembanding harga distributor dan pembuatan Surat Pesanan (SP) otomatis.

---

## Ringkasan Alur Kerja

```
[1. Upload Pricelist] 
        ↓
[2. Konfigurasi Diskon & PPN Supplier]
        ↓
[3. Input / Import Defecta (Pesanan)]
        ↓
[4. Kalkulasi Pemenang]
        ↓
[5. Unduh Surat Pesanan (Excel)]
```

---

## Langkah demi Langkah

### 1. Upload Pricelist Distributor
**Menu: Upload Batch**

1. Buka menu **Upload Batch**, lalu klik **New Upload Batch** / tombol upload file.
2. Unggah file daftar harga dari sales distributor:
   - Format yang didukung: Excel (`.xlsx`, `.xls`), `.csv`, dan dokumen PDF (seperti format PDF Solaris).
   - Masukkan diskon global pada form upload jika sales memberikan diskon umum langsung untuk seluruh file tersebut (misal: `5%` atau nominal rupiah).
3. Simpan. Sistem membaca baris judul secara otomatis, mengekstrak data obat, dan menyimpan riwayat harga ke database.

---

### 2. Atur Diskon Bertingkat & Ketentuan PPN
**Menu: Master Supplier**

Setiap distributor memiliki kebijakan harga dan diskon yang berbeda. Masuk ke menu **Suppliers**, pilih distributor yang bersangkutan, lalu klik **Edit**:

1. **Status PPN (Pricelist Termasuk PPN):**
   - **Aktifkan (Centang):** Jika harga pada file distributor sudah termasuk PPN 12% (contoh: PT ISEKAI, PT Maju Sehat, PT Tempra).
   - **Nonaktifkan:** Jika harga pada file distributor masih berupa harga dasar/HNA murni sebelum pajak. Sistem otomatis menambahkan PPN 12% saat kalkulasi harga akhir.
2. **Aturan Diskon Pembelian (Diskon Bertingkat / Tiers):**
   - Tambahkan minimal pesanan (min QTY) dan persentase diskon.
   - Contoh: Minimal QTY `40` dapat diskon `10%`, minimal QTY `30` dapat diskon `5%`.
   - Sistem otomatis menerapkan potongan harga ini ketika jumlah obat yang dipesan pada Defecta mencapai target minimal QTY.
3. **Diskon Khusus Obat Tertentu (Opsional):**
   - Jika ada obat tertentu yang memiliki promo khusus di luar diskon umum distributor, tambahkan pada tab aturan diskon khusus obat.

---

### 3. Masukkan Data Defecta (Rencana Restock)
**Menu: Transaksi → Smart Defecta (PO)**

Halaman ini berfungsi sebagai keranjang belanja terpusat apotek. Data yang tersimpan di sini tidak hilang saat halaman direfresh.

1. Buka menu **Smart Defecta (PO)**.
2. Masukkan daftar obat yang hendak dipesan:
   - **Cara Cepat (Import Excel):** Klik tombol **Import Data Pesanan (Excel)**, lalu pilih file Excel defecta dari apotek (file minimal memiliki kolom nama obat dan jumlah pesanan/QTY).
   - **Cara Manual:** Klik **Tambah Obat Manual** untuk menambahkan obat satuan langsung dari form.
3. Daftar obat yang akan dipesan langsung tampil pada tabel antrean.

---

### 4. Jalankan Kalkulasi Pemenang
**Tombol: "Kalkulasi Pemenang"**

> **Catatan Alur:** Kalkulasi dilakukan sebelum unduh file, agar pengguna dapat memeriksa hasil perbandingan harga terlebih dahulu di layar.

1. Pada halaman **Smart Defecta**, klik tombol **Kalkulasi Pemenang** di bagian atas tabel.
2. Sistem mengeksekusi perhitungan otomatis untuk setiap item obat:
   - Mencocokkan nama obat dan kekuatan dosis (misal: membedakan Amlodipine 5 mg vs 10 mg).
   - Memeriksa seluruh distributor yang memiliki stok obat tersebut.
   - Menghitung diskon bertingkat berdasarkan QTY pesanan.
   - Menghitung PPN 12% sesuai status pajak distributor.
   - Menetapkan satu distributor dengan harga netto akhir terendah sebagai pemenang.
3. Hasil kalkulasi dan urutan harga seluruh distributor langsung ditampilkan pada layar.

---

### 5. Unduh Surat Pesanan (Excel)
**Tombol: "Download Surat Pesanan (Excel)"**

1. Setelah kalkulasi selesai dan diverifikasi, klik tombol **Download Surat Pesanan (Excel)**.
2. File Excel otomatis digenerate dengan susunan:
   - **Tergrup rapi per Distributor Pemenang:** Memudahkan pemisahan pesanan saat dikirim ke sales masing-masing.
   - **Rincian Transparan:** Menampilkan QTY, Harga Dasar, Persentase Diskon, Harga Netto, Nominal PPN, Harga Final per Item, dan Total Pembelian.
   - **Pemberitahuan Khusus:** Jika ada obat pada defecta yang belum terdaftar di pricelist distributor manapun, obat tersebut tetap tercantum dengan status **"Belum Ada Pricelist"** agar apoteker tidak melewatkan item belanja yang belum terpenuhi.

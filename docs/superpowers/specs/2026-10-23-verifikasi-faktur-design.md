# Verifikasi Barang per Faktur — Desain

Tanggal: 2026-10-23
Status: draf, menunggu review
Menggantikan: `2026-10-22-sparepart-scan-mvp-design.md` (aplikasi scan stok berbasis Node.js)

## 1. Latar belakang

PT Mitra Ghanim Indosarana menjual sparepart ke bengkel/toko. Alur sekarang:

1. Admin membuat **faktur penjualan** di **Zahir**, dicetak printer dot-matrix di
   kertas continuous form rangkap (putih + pink).
2. **Gudang 1** mengambil barang sesuai faktur.
3. **Gudang 2 (QC)** mengecek ulang, lalu barang dikirim.

Masalahnya **salah ambil barang**: kode part sering beda tipis
(`S-SCS-002` vs `S-MCS-005`, `H-MYJ-005` vs `H-SYJ-002`). Pengecekan masih manual.

Setiap barang sudah berlabel barcode berisi **kode part persis seperti di faktur**
(dicek pada 9 label contoh, termasuk `H-MTE-009/010`). Gudang memakai scanner
barcode USB Newland HR3280 dalam mode keyboard.

## 2. Tujuan

Setiap faktur menjadi **sesi verifikasi**. Gudang men-scan barang yang diambil;
sistem langsung menunjukkan barang mana yang **sudah benar (hijau)** dan mana yang
**salah (merah)**. Riwayat setiap scan tersimpan, dan barang yang keluar tercatat.

**Ukuran keberhasilan:** dengan faktur contoh `00066365` (Akbar Jaya Motor HSF,
9 baris) dan barang aslinya, gudang bisa menyelesaikan satu sesi hanya dengan scanner,
dan setiap barang yang tidak ada di faktur atau kelebihan jumlah langsung ditandai.

## 3. Ruang lingkup

### Termasuk tahap ini

- Unggah faktur sebagai **PDF** (utama) atau **foto/scan** (cadangan).
- Pembacaan faktur **offline di browser**: `pdf.js` untuk PDF, `Tesseract.js` untuk foto.
- Layar **konfirmasi** hasil bacaan sebelum sesi dibuat.
- Daftar sesi di layar gudang, lalu scan pertama (wajib) dan scan QC (opsional).
- Aturan cocok / salah / kelebihan, dengan warna, ikon, dan bunyi.
- Ketik kode manual sebagai cadangan, ditandai di riwayat.
- Riwayat scan per sesi dan laporan barang keluar.
- Foto/PDF faktur dihapus otomatis setelah 60 hari; data sesi disimpan permanen.

### Ditunda

- Login dan hak akses (risiko dicatat di bagian 10).
- Empat scanner di satu layar.
- Cetak struk termal.
- Revisi faktur (nomor sama dicetak ulang setelah sesi mulai di-scan).
- Faktur lebih dari satu halaman.
- Deploy ke Hostinger dan auto-deploy dari GitHub.

### Dihapus

Seluruh aplikasi Node.js lama (scan stok, daftar part, kamera HP) dihapus dari repo.
Riwayatnya tetap ada di git.

## 4. Pengguna dan perangkat

| Peran | Komputer | Tugas |
|---|---|---|
| Admin | PC admin (yang ada Zahir) | Cetak faktur ke "Microsoft Print to PDF", unggah, konfirmasi |
| Gudang | PC gudang + scanner Newland | Buka sesi, scan, selesaikan, scan QC |

Keduanya membuka aplikasi lewat Chrome. Untuk tahap ini aplikasi dijalankan lokal
(Laragon); nanti dipindah ke hosting supaya kedua PC bisa mengakses.

## 5. Alur

### 5.1 Admin: faktur baru (`/faktur/baru`)

1. Pilih file PDF atau foto (JPG/PNG), maksimal 10 MB.
2. Browser membaca file:
   - PDF: teks diambil dengan `pdf.js`, dikelompokkan per baris berdasarkan posisi vertikal.
   - Foto: teks dibaca dengan `Tesseract.js` (bahasa Inggris, offline). Tampil progres.
3. Teks diurai oleh `invoice-parser.js` (bagian 6) menjadi: nomor faktur, penerima,
   tanggal (opsional), dan daftar baris (kode part, nama barang, jumlah).
4. **Layar konfirmasi**: gambar/PDF faktur di kiri, tabel hasil di kanan. Semua isian
   bisa diedit; baris bisa ditambah atau dihapus. Isian yang formatnya janggal diberi
   tanda kuning (misalnya kode mengandung huruf `O` di posisi angka).
5. **Simpan** → server memvalidasi, menyimpan file asli dan data, lalu sesi berstatus
   **Menunggu**.

Nomor faktur yang sudah ada:
- belum ada scan → data lama diganti dengan yang baru;
- sudah ada scan → ditolak: "Faktur 00066365 sudah mulai di-scan, tidak bisa diganti."

### 5.2 Gudang: daftar sesi (`/gudang`)

- Faktur berstatus Menunggu dan Diproses, terbaru di atas.
- Kolom cari nomor faktur atau nama penerima.
- Tab "Selesai" untuk sesi yang sudah selesai (untuk scan QC).

### 5.3 Gudang: sesi (`/gudang/sesi/{id}`)

Header: **nomor faktur di kiri**, **nama penerima di kanan**, di bawahnya tahap
(`Scan pertama` / `Scan QC`) dan progres (`7 dari 14 barang`).

Tabel: kode part, nama barang, `ter-scan / jumlah`, ikon status.

| Kejadian | Tampilan | Dicatat |
|---|---|---|
| Kode ada di faktur, belum lengkap | +1; baris lengkap → **hijau ✓**, sebagian → abu-abu `1/2` | `ok` |
| Kode ada, sudah lengkap | Tidak ditambah; **notif merah** "S-TKS-002 sudah lengkap (2/2)" + bunyi error | `over_qty` |
| Kode tidak ada di faktur | **Baris merah ✗** di bawah tabel: "H-MTE-005 tidak ada di faktur", saran kode termirip; bunyi error. Hanya peringatan, sesi tidak dikunci | `not_in_invoice` |

- Input dari scanner ditangkap otomatis (pembeda ketikan cepat yang sudah ada).
- Kotak **ketik kode manual** tersedia; scan lewat sini ditandai `manual`.
- Saran kode termirip memakai jarak edit (Levenshtein) ke kode di faktur, maksimal jarak 3.
- Saat semua baris hijau, tombol **Selesai** aktif. Selesai → status **Selesai**,
  waktu dicatat.
- Setelah Selesai, tombol **Mulai scan QC** membuka tahap QC dengan hitungan terpisah
  (aturan sama). Semua hijau → **Selesai QC**. Tahap ini opsional.
- Halaman memuat ulang status dari server setelah setiap scan, sehingga membuka sesi
  yang sama di tab lain tetap konsisten.

### 5.4 Laporan barang keluar (`/laporan`)

- Filter rentang tanggal (default hari ini) dan nama penerima.
- Berisi faktur yang sudah **Selesai** (scan pertama), dengan barangnya.
- Ringkasan total jumlah per kode part pada rentang tersebut.

### 5.5 Riwayat sesi

Di halaman sesi, panel "Riwayat scan" berisi semua kejadian: waktu (WIB), tahap,
kode, hasil, manual atau scanner.

## 6. Pengurai faktur (`public/js/invoice-parser.js`)

Fungsi murni, diuji dengan teks contoh. Masukan: daftar baris teks. Keluaran:
`{ number, customer, date, items: [{ code, name, qty }], warnings: [] }`.

Aturan (dari faktur Zahir contoh):
- Nomor: baris berisi `No Faktur` → teks setelah `:`, hanya digit.
- Penerima: baris berisi `Kepada` → teks setelah `:`.
- Tanggal: baris berisi `Tanggal` → teks setelah `:` (disimpan apa adanya, opsional).
- Baris barang ada di antara judul kolom `Nama Barang` dan baris yang berisi
  terbilang/`Dasar Pengenaan Pajak`.
- Baris barang diawali kode `^[A-Z]-[A-Z0-9]+(-[A-Z0-9/]+)+`, lalu nama, lalu
  jumlah (bilangan bulat) diikuti satuan (`Pcs`, `Set`, dll.). Harga diabaikan.
- Baris tanpa kode di bagian barang = lanjutan nama baris sebelumnya
  (contoh `(84306-BZ200)` pada `S-TKS-002`).
- Peringatan dibuat untuk: kode dengan `O` di posisi yang biasanya angka, jumlah tidak
  terbaca, nomor atau penerima kosong.

## 7. Data (MySQL)

### `invoices`
| Kolom | Tipe | Aturan |
|---|---|---|
| id | bigint | PK |
| number | varchar(32) | wajib, unik |
| customer_name | varchar(150) | wajib |
| invoice_date | varchar(60) | opsional, teks dari faktur |
| source_type | enum(`pdf`,`photo`) | wajib |
| file_path | varchar(255) | null setelah dihapus |
| file_purged_at | timestamp | null |
| status | enum(`waiting`,`picking`,`picked`,`qc`,`qc_done`) | default `waiting` |
| picked_at, qc_done_at | timestamp | null |
| timestamps | | |

### `invoice_items`
| Kolom | Tipe | Aturan |
|---|---|---|
| id | bigint | PK |
| invoice_id | FK | hapus bertingkat |
| line_no | smallint | urutan di faktur |
| part_code | varchar(64) | wajib |
| part_name | varchar(255) | wajib |
| qty | int | 1–10.000 |
| picked_qty | int | default 0, tidak melebihi qty |
| qc_qty | int | default 0, tidak melebihi qty |

Kode yang sama muncul dua kali di satu faktur digabung menjadi satu baris saat simpan
(jumlah dijumlahkan).

### `scan_events`
| Kolom | Tipe | Aturan |
|---|---|---|
| id | bigint | PK |
| invoice_id | FK | |
| invoice_item_id | FK | null untuk `not_in_invoice` |
| stage | enum(`pick`,`qc`) | |
| code | varchar(64) | kode yang di-scan |
| result | enum(`ok`,`over_qty`,`not_in_invoice`) | |
| manual | boolean | |
| created_at | timestamp | |

Penambahan `picked_qty`/`qc_qty` dilakukan dengan
`UPDATE ... SET picked_qty = picked_qty + 1 WHERE id = ? AND picked_qty < qty`
dalam transaksi bersama penulisan `scan_events`, sehingga tidak bisa melebihi jumlah.

## 8. API (JSON, dipakai JavaScript halaman)

| Method | Path | Fungsi |
|---|---|---|
| POST | `/api/invoices` | Simpan faktur hasil konfirmasi + file (multipart) |
| GET | `/api/invoices?status=&q=` | Daftar sesi |
| GET | `/api/invoices/{id}` | Detail sesi, baris, kejadian terakhir |
| POST | `/api/invoices/{id}/scans` | `{ code, stage, manual }` → hasil + status terbaru |
| POST | `/api/invoices/{id}/complete` | `{ stage }`, hanya jika semua baris lengkap |
| GET | `/api/reports/outgoing?from=&to=&q=` | Laporan barang keluar |

Format respons: `{ success, data, error }`, seperti aplikasi lama.

## 9. Teknologi

- **Laravel 12**, PHP 8.2+, MySQL (Laragon di lokal).
- Tampilan **Blade + JavaScript biasa**, tanpa build step. CSS memakai palet putih
  lembut / hitam lembut yang sudah ada.
- `pdf.js` dan `Tesseract.js` (beserta data bahasa `eng`) disimpan di
  `public/vendor/`, tidak memakai CDN, supaya tetap jalan saat internet lambat.
- File faktur disimpan di `storage/app/invoices/` (disk `local`, tidak publik),
  disajikan lewat route yang mengecek sesi ada.
- Perintah terjadwal `invoices:purge-files` setiap hari: hapus file yang berumur
  lebih dari 60 hari, isi `file_purged_at`.
- Bunyi sukses/error memakai Web Audio (sama seperti sekarang).

## 10. Keamanan dan risiko

- **Tanpa login di tahap ini.** Aman selama dijalankan lokal. Sebelum dipasang di
  hosting dengan data klien asli, minimal kata sandi bersama harus dipasang.
- Semua input divalidasi di server (Form Request Laravel). Query lewat Eloquent/
  query builder (parameter). Output Blade di-escape; JS memakai `textContent`.
- Unggahan dibatasi tipe (`pdf`, `jpg`, `jpeg`, `png`) dan ukuran (10 MB);
  nama file diganti acak.
- CSRF aktif untuk semua POST.

## 11. Pengujian

- **PHPUnit feature test**: simpan faktur (termasuk nomor duplikat dengan/tanpa scan),
  scan `ok` / `over_qty` / `not_in_invoice`, selesai ditolak jika belum lengkap,
  QC terpisah, laporan, perintah hapus file 60 hari.
- **Unit test JS** (`node --test`): `invoice-parser.js` dengan teks faktur
  `00066365` (hasil PDF dan hasil OCR yang mengandung salah baca), pencarian kode
  termirip, pembeda ketikan scanner (sudah ada).
- **Uji manual**: unggah PDF faktur contoh, konfirmasi, scan 9 label asli
  (14 barang; label yang sama di-scan ulang untuk jumlah lebih dari 1) dengan scanner
  Newland, coba barang salah dan kelebihan, selesaikan,
  scan QC, cek laporan.

## 12. Hal yang perlu didapat dari klien nanti

- Contoh PDF hasil "Microsoft Print to PDF" dari Zahir (untuk memastikan berisi teks).
- Faktur lebih dari satu halaman.
- Alur revisi faktur.
- Merk/ukuran printer termal.
- Daftar pengguna untuk login.

# Verifikasi Faktur Gudang

Aplikasi untuk mencegah salah ambil barang. Setiap faktur penjualan dari Zahir menjadi
**sesi**; gudang men-scan label barcode barang dan sistem langsung menandai:

- **hijau ✓**: kode ada di faktur dan jumlahnya sudah lengkap,
- **merah ✗**: kode tidak ada di faktur (dengan saran kode termirip),
- **notif merah**: barang sudah lengkap tetapi di-scan lagi (tidak dihitung).

Desain lengkap: [docs/superpowers/specs/2026-10-23-verifikasi-faktur-design.md](docs/superpowers/specs/2026-10-23-verifikasi-faktur-design.md).

> **Belum ada login.** Aman untuk dijalankan lokal. Sebelum dipasang di hosting dengan data
> klien asli, pasang minimal kata sandi bersama.

## Prasyarat

- PHP 8.2+ dan MySQL 8 (Laragon sudah menyediakan keduanya)
- Composer
- Node.js — hanya untuk menjalankan test JavaScript dan menyalin library (`npm run vendor`)

## Menjalankan di laptop

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Buat database `sparepart` di MySQL (lewat HeidiSQL di Laragon atau perintah
`CREATE DATABASE sparepart;`), lalu:

```bash
php artisan migrate
php artisan serve
```

Buka `http://localhost:8000`.

Penghapusan otomatis foto/PDF faktur yang berumur lebih dari 60 hari berjalan lewat scheduler:

- lokal: `php artisan schedule:work`
- hosting: cron setiap menit `php /path/ke/artisan schedule:run`

## Cara pakai

**Admin (komputer Zahir)**
1. Cetak faktur di Zahir ke **Microsoft Print to PDF** (atau foto/scan kertas fakturnya).
2. Buka **Faktur baru**, pilih file. PDF dibaca langsung; foto dibaca dengan OCR di browser (offline).
3. Cek hasil bacaan di samping gambar faktur. Bagian kuning perlu diperiksa. Lalu **Simpan faktur**.

**Gudang (komputer dengan scanner)**
1. Buka **Gudang**, pilih faktur di tab "Perlu di-scan".
2. Scan label setiap barang. Jumlah 2 berarti scan 2 kali.
3. Setelah semua baris hijau, klik **Selesai**.
4. (Opsional) Klik **Mulai scan QC** untuk pengecekan ulang, lalu **Selesai QC**.

Label rusak bisa diketik di kotak "Ketik kode"; di riwayat ditandai "diketik".

**Laporan** menampilkan barang keluar per rentang tanggal (WIB) dan total per kode part.

## Test

```bash
php artisan test
npm test
```

## Library pembaca faktur

`pdf.js` dan `Tesseract.js` (beserta data bahasa Inggris) disimpan di `public/vendor/`
supaya jalan tanpa CDN dan tanpa build di hosting. Setelah mengubah versinya di
`package.json`:

```bash
npm install
npm run vendor
```

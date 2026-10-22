# Sparepart Scan MVP — Desain

Tanggal: 2026-10-22
Status: draf, menunggu review

## 1. Latar belakang

Customer adalah toko sparepart mobil dengan satu cabang: satu gudang (warehouse)
dan satu storefront. Ada sekitar 11.000 jenis part. Banyak part yang bentuknya
mirip tetapi part number-nya berbeda, sehingga sering salah ambil. Pencatatan
sekarang masih memakai buku tulis.

Semua part sudah memiliki barcode dari pabrik. Customer tidak membutuhkan fitur
kasir, diskon, atau integrasi dengan sistem lain. Sistem dipakai secara internal.

## 2. Tujuan

Karyawan bisa memakai kamera HP masing-masing untuk:

1. Scan sebuah part dan langsung melihat part number, deskripsi, harga, dan stok,
   tanpa perlu cek manual.
2. Mencatat barang **masuk** dari supplier ke gudang (stok bertambah).
3. Mencatat barang **keluar** dari gudang ke storefront (stok berkurang).

**Ukuran keberhasilan MVP:** dengan barang asli yang sudah memiliki barcode,
tim bisa mendaftarkan part, scan masuk, scan keluar, dan melihat stok serta
riwayatnya dari HP. Semua langkah itu tidak memerlukan pencatatan di kertas.

## 3. Alur bisnis

```
SUPPLIER ──► WAREHOUSE                          STOREFRONT
             scan MASUK → stok +                pembeli minta part X
                                                       │
             warehouse cari part X  ◄──── toko kasih tahu warehouse (di luar sistem)
                     │
             scan KELUAR → stok −  ──► barang dibawa ke toko

Siapa saja, kapan saja: scan CEK → info part, stok tidak berubah
```

## 4. Ruang lingkup

### Termasuk MVP

- Halaman **Scan** dengan tiga mode: Cek, Masuk, Keluar.
- Pembacaan barcode lewat kamera HP di browser, plus input barcode manual
  sebagai cadangan.
- Mendaftarkan part baru ketika barcode belum dikenal.
- Halaman **Daftar Part**: cari dan edit data part.
- Halaman **Riwayat**: daftar pergerakan masuk dan keluar.
- Server berjalan di laptop dan diakses dari HP lewat Cloudflare Quick Tunnel.

### Di luar MVP (ditunda)

- Login dan hak akses per peran (owner, admin gudang, QC, storefront).
- Permintaan barang dari storefront ke warehouse di dalam aplikasi, termasuk
  pengecekan otomatis "part yang di-scan cocok dengan yang diminta".
- Stock opname.
- Import data part dari Excel.
- Foto part.
- Laporan dan ekspor.
- Hosting permanen dan domain sendiri.

Struktur data dibuat agar fitur-fitur ini bisa ditambahkan tanpa mengubah tabel
yang sudah ada secara besar-besaran.

## 5. Layar

Semua layar dibuat untuk HP (mobile-first) dan tetap bisa dipakai di laptop.
Navigasi bawah: **Scan · Part · Riwayat**.

### 5.1 Scan (`/`)

- Tombol mode di bagian atas: `Cek` (bawaan), `Masuk`, `Keluar`. Mode terakhir
  diingat di browser (localStorage).
- Kamera menyala begitu halaman dibuka. Kalau izin kamera ditolak, tampilkan
  pesan cara mengizinkannya, dan input manual tetap bisa dipakai.
- Input manual: kolom barcode dan tombol **Cari**.
- Setelah barcode terbaca, kamera berhenti sementara dan muncul kartu hasil.
  Tombol **Scan lagi** menyalakan kamera kembali.
- Pembacaan barcode yang sama dalam 2 detik diabaikan (mencegah dobel).
- HP bergetar singkat ketika barcode terbaca (`navigator.vibrate`), bila didukung.

**Kartu hasil (barcode terdaftar):**

- Part number (huruf besar dan paling menonjol), deskripsi, barcode.
- Harga umum, harga langganan, harga grosir dalam format Rupiah.
- Stok saat ini.
- Mode Cek: hanya informasi.
- Mode Masuk atau Keluar: kolom jumlah (bawaan 1), kolom catatan (opsional),
  dan tombol **Simpan Masuk** atau **Simpan Keluar**. Setelah berhasil, tampil
  pesan "Stok sekarang: N" lalu kembali ke mode scan.

**Barcode belum terdaftar:**

- Tampil pesan "Barcode belum terdaftar" dan form **Daftarkan part**: barcode
  (terisi otomatis, tidak bisa diubah), part number, deskripsi, tiga harga.
- Stok part baru dimulai dari 0. Kalau sedang di mode Masuk, setelah part
  didaftarkan langsung lanjut ke form jumlah masuk.

### 5.2 Daftar Part (`/parts.html`)

- Kolom pencarian, mencocokkan part number, deskripsi, atau barcode
  (sebagian teks, tidak peka huruf besar/kecil). Maksimal 50 hasil, diurutkan
  berdasarkan part number.
- Setiap baris: part number, deskripsi, stok.
- Ketuk satu baris untuk membuka form edit: part number, deskripsi, tiga harga.
  Barcode dan stok tidak bisa diedit di sini. Stok hanya berubah lewat
  pergerakan masuk dan keluar.

### 5.3 Riwayat (`/history.html`)

- 100 pergerakan terbaru, yang terbaru di atas.
- Setiap baris: waktu (WIB), jenis (Masuk/Keluar), part number, jumlah,
  catatan.

## 6. Data

Database SQLite dalam satu file: `data/sparepart.db`.

### Tabel `parts`

| Kolom | Tipe | Aturan |
|---|---|---|
| id | INTEGER | primary key |
| barcode | TEXT | wajib, unik, 1–64 karakter |
| part_number | TEXT | wajib, 1–64 karakter |
| description | TEXT | wajib, 1–500 karakter |
| price_retail | INTEGER | Rupiah, ≥ 0, harga umum |
| price_member | INTEGER | Rupiah, ≥ 0, harga langganan |
| price_wholesale | INTEGER | Rupiah, ≥ 0, harga grosir |
| stock | INTEGER | ≥ 0, bawaan 0 |
| created_at | TEXT | waktu ISO 8601 (UTC) |
| updated_at | TEXT | waktu ISO 8601 (UTC) |

Part number tidak dibuat unik, karena jawaban customer menyebut barang beda
merk tidak dibedakan kodenya. Barcode yang menjadi kunci unik.

### Tabel `movements`

| Kolom | Tipe | Aturan |
|---|---|---|
| id | INTEGER | primary key |
| part_id | INTEGER | wajib, foreign key ke `parts.id` |
| type | TEXT | `in` atau `out` |
| qty | INTEGER | 1–100.000 |
| stock_after | INTEGER | stok setelah pergerakan ini |
| note | TEXT | opsional, maksimal 200 karakter |
| created_at | TEXT | waktu ISO 8601 (UTC) |

`stock_after` disimpan agar riwayat bisa ditelusuri tanpa menghitung ulang.
Nanti stock opname bisa ditambahkan sebagai `type = 'adjust'`.

### Aturan stok

- Masuk: `stock = stock + qty`.
- Keluar: hanya berhasil kalau `stock >= qty`. Jika tidak, tidak ada yang
  disimpan dan pengguna melihat pesan "Stok tidak cukup (stok: N)".
- Perubahan stok dan penyimpanan pergerakan dilakukan dalam satu transaksi
  database. Pengurangan memakai `UPDATE ... WHERE id = ? AND stock >= ?`,
  sehingga dua HP yang menyimpan keluar bersamaan tidak membuat stok minus.

## 7. API

Semua respons memakai format yang sama:

```json
{ "success": true, "data": { }, "error": null }
{ "success": false, "data": null, "error": "Pesan yang bisa dibaca pengguna" }
```

| Method | Path | Fungsi | Status |
|---|---|---|---|
| GET | `/api/parts/by-barcode/:barcode` | Ambil part berdasarkan barcode | 200, 404 |
| GET | `/api/parts?q=` | Cari part (maks. 50) | 200 |
| POST | `/api/parts` | Daftarkan part baru | 201, 400, 409 (barcode sudah ada) |
| PUT | `/api/parts/:id` | Ubah data part (bukan barcode dan stok) | 200, 400, 404 |
| POST | `/api/movements` | Catat masuk/keluar `{ part_id, type, qty, note }` | 201, 400, 404, 409 (stok tidak cukup) |
| GET | `/api/movements?limit=` | Riwayat terbaru (bawaan 100, maks. 500) | 200 |

`POST /api/movements` mengembalikan pergerakan yang baru dibuat beserta stok
terbaru part tersebut.

## 8. Validasi dan penanganan error

- Semua input divalidasi di server sebelum masuk database: teks di-trim,
  panjang dicek, angka harus bilangan bulat dalam batas yang ditentukan.
  Validasi di browser hanya untuk kenyamanan.
- Query database selalu memakai parameter, tidak pernah menyambung string.
- Teks dari database ditampilkan memakai `textContent`, bukan `innerHTML`,
  untuk mencegah XSS.
- Error tak terduga dicatat lengkap di konsol server, sedangkan pengguna hanya
  melihat "Terjadi kesalahan di server. Coba lagi."
- Di browser, kegagalan jaringan menampilkan "Tidak bisa terhubung ke server.
  Pastikan laptop dan tunnel masih berjalan."

## 9. Arsitektur dan teknologi

- **Runtime:** Node.js 24 (sudah terpasang).
- **Server:** Express. Melayani API dan file statis dari `public/`.
- **Database:** modul bawaan `node:sqlite`, jadi tidak perlu instal atau
  kompilasi database terpisah.
- **Frontend:** HTML, CSS, dan JavaScript biasa, tanpa framework dan tanpa
  build step.
- **Pembaca barcode:** library `html5-qrcode` (dipasang lewat npm dan disajikan
  dari server sendiri). Format yang diaktifkan: EAN-13, EAN-8, UPC-A, UPC-E,
  Code 128, Code 39, dan QR. Library ini berjalan di Chrome Android maupun
  Safari iOS.
- **Akses dari HP:** `cloudflared tunnel --url http://localhost:3000`
  menghasilkan URL `https://….trycloudflare.com`. HTTPS diperlukan agar browser
  mengizinkan kamera. Gratis, tanpa akun.
- **Konfigurasi:** `PORT` (bawaan 3000) dan `DB_PATH` (bawaan
  `data/sparepart.db`) lewat environment variable.

### Struktur folder

```
sparepart/
  package.json
  src/
    server.js            # titik masuk: buka DB, jalankan app
    app.js               # membuat Express app (dipakai juga oleh test)
    db.js                # koneksi SQLite dan pembuatan tabel
    validation.js        # validasi input
    respond.js           # helper format respons
    repositories/
      parts.js
      movements.js
    routes/
      parts.js
      movements.js
  public/
    index.html           # Scan
    parts.html
    history.html
    css/style.css
    js/api.js            # pemanggil API bersama
    js/format.js         # format Rupiah dan waktu WIB
    js/scan.js
    js/parts.js
    js/history.js
  test/
  data/                  # file database (diabaikan git)
  docs/
```

## 10. Keamanan untuk tahap testing

- MVP belum memiliki login. Siapa pun yang tahu URL tunnel bisa membaca dan
  mengubah data. Karena itu:
  - URL hanya dibagikan ke tim.
  - Testing memakai data barang contoh, bukan data harga asli customer.
  - Tunnel dimatikan setelah selesai testing.
- Login wajib ditambahkan sebelum aplikasi dipakai dengan data asli.

## 11. Pengujian

- **Unit test** (`node:test`): validasi input, format Rupiah dan waktu.
- **Integration test** (`node:test` + `fetch` ke app yang berjalan dengan
  SQLite `:memory:`): semua endpoint API, termasuk barcode duplikat, stok tidak
  cukup, input tidak valid, dan part yang tidak ditemukan.
- **Coverage:** target minimal 80% untuk kode server, diukur dengan
  `node --test --experimental-test-coverage`.
- **Uji manual di HP** (pemindaian kamera tidak bisa diotomatiskan dengan baik):
  1. Buka URL tunnel di Chrome Android, izinkan kamera.
  2. Scan barang yang belum terdaftar, lalu daftarkan.
  3. Mode Masuk: scan dan simpan 5 unit. Stok menjadi 5.
  4. Mode Keluar: simpan 2 unit. Stok menjadi 3.
  5. Mode Keluar: coba 10 unit. Muncul pesan stok tidak cukup dan stok tetap 3.
  6. Mode Cek: scan barang yang sama. Info dan stok 3 tampil.
  7. Riwayat menampilkan dua pergerakan dengan waktu WIB yang benar.
  8. Ulangi langkah 1, 2, dan 6 di iPhone (Safari) bila tersedia.

## 12. Pertanyaan untuk visit Jumat, 23 Oktober 2026

1. Data 11.000 part (barcode, part number, deskripsi, harga) tersedia dalam
   bentuk apa? Excel dari supplier, buku tulis, atau belum ada?
2. Apakah satu barcode selalu berarti satu jenis part? Apakah ada part berbeda
   yang barcode-nya sama, atau satu part dengan beberapa barcode (misalnya
   kemasan berbeda)?
3. Bagaimana storefront memberi tahu warehouse barang yang dibutuhkan?
4. Siapa saja yang memakai aplikasi, dan siapa yang boleh mencatat
   masuk/keluar atau mengubah harga?
5. Apakah stok perlu dicatat sesuai lokasi rak?
6. Apakah foto part dibutuhkan untuk membedakan part yang mirip?

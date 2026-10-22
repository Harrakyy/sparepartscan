# Gudang Sparepart — Scan MVP

Aplikasi web internal untuk gudang sparepart. Barcode part di-scan dengan kamera HP untuk:

- **Cek**: melihat part number, deskripsi, harga (umum/langganan/grosir), dan stok.
- **Masuk**: mencatat barang dari supplier masuk ke gudang (stok bertambah).
- **Keluar**: mencatat barang dari gudang ke toko (stok berkurang, tidak bisa minus).

Barcode yang belum terdaftar bisa langsung didaftarkan dari layar scan.

Desain lengkapnya ada di [docs/superpowers/specs/2026-10-22-sparepart-scan-mvp-design.md](docs/superpowers/specs/2026-10-22-sparepart-scan-mvp-design.md).

## Prasyarat

- Node.js 24 atau lebih baru (`node --version`)
- cloudflared, untuk mengakses aplikasi dari HP:

  ```bash
  winget install --id Cloudflare.cloudflared
  ```

## Menjalankan

```bash
npm install
npm start
```

Server jalan di `http://localhost:3000`. Data tersimpan di `data/sparepart.db`.

Pengaturan lewat environment variable:

| Variabel | Bawaan | Fungsi |
|---|---|---|
| `PORT` | `3000` | Port server |
| `DB_PATH` | `data/sparepart.db` | Lokasi file database |

## Membuka dari HP

Kamera HP hanya bisa dipakai di situs HTTPS. Di terminal kedua, jalankan:

```bash
cloudflared tunnel --url http://localhost:3000
```

Salin link `https://….trycloudflare.com` yang muncul, lalu buka di browser HP dan izinkan akses kamera. Link ini berganti setiap kali tunnel dijalankan ulang. Gratis dan tanpa akun.

## Keamanan selama testing

Aplikasi ini **belum punya login**. Siapa pun yang tahu link tunnel bisa membaca dan mengubah data. Karena itu:

- Bagikan link hanya ke tim.
- Pakai data barang contoh, bukan data harga asli customer.
- Matikan tunnel (Ctrl+C) setelah selesai testing.

Login wajib ditambahkan sebelum aplikasi dipakai dengan data asli.

## Test

```bash
npm test
npm run coverage
```

`npm run coverage` gagal kalau line coverage di bawah 80%.

## Uji manual di HP

1. Buka link tunnel di Chrome Android, izinkan kamera.
2. Scan barang yang belum terdaftar, lalu daftarkan.
3. Mode Masuk: scan dan simpan 5 unit. Stok menjadi 5.
4. Mode Keluar: simpan 2 unit. Stok menjadi 3.
5. Mode Keluar: coba 10 unit. Muncul "Stok tidak cukup (stok: 3)" dan stok tetap 3.
6. Mode Cek: scan barang yang sama. Info dan stok 3 tampil.
7. Riwayat menampilkan dua pergerakan dengan waktu WIB yang benar.
8. Ulangi langkah 1, 2, dan 6 di iPhone (Safari) bila tersedia.

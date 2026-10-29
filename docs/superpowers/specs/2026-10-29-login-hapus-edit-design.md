# Login, hak akses, hapus faktur, dan edit jumlah — desain

Tanggal: 2026-10-29
Status: disetujui lewat diskusi, menunggu review spec

## Tujuan

Masukan dari tim gudang dan keputusan klien:

1. **Hapus faktur.** Faktur yang salah diupload (misal tertulis 2 pcs, stok tinggal 1) dibuat
   ulang di Zahir dengan **nomor yang sama**. App harus bisa menghapus faktur lama supaya
   faktur baru bernomor sama bisa diupload.
2. **Edit jumlah.** Setelah barang keluar lalu sebagian diretur (keluar 2, retur 1), jumlah
   keluar di app dan di laporan harus sama dengan Zahir (1).
3. **Login dan hak akses.** Kedua fitur di atas hanya untuk **superadmin**; fitur lain untuk
   **admin**. Login biasa (email + password), tanpa Google, tanpa 2FA.

**Berhasil bila:** orang tanpa akun tidak bisa membuka halaman atau API apa pun; admin tidak
bisa menghapus/mengedit walau memanggil API langsung; faktur yang dihapus bisa diupload ulang
dengan nomor yang sama; laporan barang keluar memakai jumlah setelah diedit.

## Keputusan

| Hal | Keputusan |
|---|---|
| Cara login | Email + password, centang "Ingat saya"; tanpa Google, tanpa 2FA |
| Akun | Hanya dibuat superadmin di menu Pengguna; tidak ada pendaftaran sendiri |
| Reset password | Hanya oleh superadmin (set password baru); tanpa email/SMTP |
| Laptop gudang | Login sekali dengan "Ingat saya", tetap masuk sampai Keluar |
| Akun awal | Perintah `php artisan app:superadmin`, password diketik saat dijalankan |
| Hapus faktur | Superadmin, semua status, konfirmasi ketik ulang nomor; hilang total |
| Edit jumlah | Superadmin, setelah scan pertama selesai; hanya mengurangi (0–jumlah faktur); alasan wajib |

## Data

**`users`** (tabel bawaan, ditambah kolom):

| Kolom | Tipe | Catatan |
|---|---|---|
| role | enum superadmin, admin | default admin |
| is_active | boolean | default true; akun nonaktif tidak bisa login |
| last_login_at | timestamp null | |

**`invoice_items`** ditambah `out_qty` (unsigned int, null). Null = sama dengan `qty`.
Jumlah keluar = `out_qty ?? qty`.

**`qty_adjustments`** — riwayat edit jumlah.

| Kolom | Tipe | Catatan |
|---|---|---|
| id | bigint | |
| invoice_id | FK invoices | cascade delete |
| invoice_item_id | FK invoice_items | cascade delete |
| user_id | FK users null | null on delete |
| from_qty, to_qty | unsigned int | |
| note | varchar(255) | alasan, wajib |
| created_at | timestamp | |

## Aturan

### Login
1. Semua halaman web dan `/api/*` wajib login, kecuali `/login` (GET/POST).
2. Login gagal: pesan umum "Email atau password salah". Akun nonaktif: "Akun ini dinonaktifkan,
   hubungi superadmin". Maksimal 5 percobaan per menit per email+IP (throttle), lalu
   "Terlalu banyak percobaan, coba lagi dalam N detik".
3. "Ingat saya" memakai remember token Laravel; sesi biasa tetap `SESSION_LIFETIME` (120 menit).
4. Sesi lama dinonaktifkan saat akun dinonaktifkan atau password diganti superadmin (remember
   token diganti; akun nonaktif ditolak di setiap request).
5. API tanpa login → 401 JSON; `api.js` mengarahkan ke `/login`. Halaman tanpa login → redirect `/login`.

### Peran
6. Gate `superadmin`. Endpoint hapus faktur, edit jumlah, dan semua endpoint Pengguna ditolak
   **403** untuk admin ("Hanya superadmin yang boleh melakukan ini").
7. Tombol/menu khusus superadmin tidak dirender untuk admin.

### Pengguna (superadmin)
8. Tambah: nama (wajib, ≤100), email (wajib, unik), peran, password (wajib, ≥8 karakter).
9. Ubah peran, aktif/nonaktif, set password baru (≥8 karakter).
10. Superadmin tidak bisa mengubah peran atau menonaktifkan **dirinya sendiri**; selalu tersisa
    minimal satu superadmin aktif.
11. Tidak ada hapus pengguna (nonaktifkan saja), supaya riwayat edit tetap menunjuk orangnya.

### Hapus faktur
12. Superadmin, semua status. Body `{confirm_number}` harus sama dengan nomor faktur, kalau
    tidak 422 "Nomor konfirmasi tidak cocok".
13. Terhapus: faktur, barang, scan, cetak, retur terkait, riwayat edit (cascade). File PDF
    dihapus bila tidak dipakai faktur lain (file laporan bersama).
14. Nomor faktur langsung bisa dipakai lagi (upload biasa maupun laporan).

### Edit jumlah
15. Hanya setelah scan pertama selesai (`picked_at` terisi), selain itu 409
    "Selesaikan scan pertama dulu".
16. Per barang: `0 ≤ jumlah baru ≤ qty`; alasan wajib (≤255). Barang yang jumlahnya tidak
    berubah tidak dicatat. Tidak ada perubahan sama sekali → 422 "Tidak ada jumlah yang berubah".
17. Tiap perubahan dicatat di `qty_adjustments` dengan user yang mengubah; edit boleh berkali-kali.
18. Laporan barang keluar (daftar dan total) dan struk memakai jumlah keluar (`out_qty ?? qty`).
    Tanggal keluar tetap tanggal cetak pertama. Scan QC tetap dibandingkan dengan `qty`.

## API

| Method | Path | Akses | Fungsi |
|---|---|---|---|
| GET | /login | tamu | form login |
| POST | /login | tamu | `{email, password, remember}` |
| POST | /logout | login | keluar |
| DELETE | /api/invoices/{id} | superadmin | `{confirm_number}` |
| POST | /api/invoices/{id}/adjust | superadmin | `{note, items: [{invoice_item_id, qty}]}` → detail |
| GET | /api/users | superadmin | daftar pengguna |
| POST | /api/users | superadmin | tambah |
| PATCH | /api/users/{id} | superadmin | `{role?, is_active?, password?}` |

Detail faktur (`GET /api/invoices/{id}`) ditambah `out_qty` per barang dan `adjustments`
(terbaru dulu: waktu, nama user, kode, dari, ke, alasan). Layout menerima `currentUser`
(nama, peran) untuk topbar dan tombol khusus superadmin.

## Layar

- **`/login`**: logo, email, password, "Ingat saya", tombol Masuk; gaya mengikuti app.
- **Topbar**: nama pengguna + tombol Keluar; menu **Pengguna** hanya untuk superadmin.
- **`/pengguna`** (superadmin): tabel nama, email, peran, status, login terakhir; form tambah;
  per baris: ubah peran, aktif/nonaktif, set password.
- **Sesi gudang** (superadmin):
  - Tombol **Hapus faktur** (merah) di toolbar → dialog: ringkasan (sudah di-scan N barang,
    dicetak N kali), kolom ketik nomor faktur, tombol Hapus aktif bila cocok → ke `/gudang`.
  - Tombol **Edit jumlah** (setelah scan pertama selesai) → kolom jumlah jadi isian angka per
    barang + kolom alasan + Simpan/Batal. Barang yang diedit tampil "2 → 1"; kartu
    **Riwayat perubahan** di bawah tabel.

## Pengujian

- `tests/Feature/AuthTest.php`: tamu diarahkan/401; login benar, salah, akun nonaktif; ingat saya;
  throttle; keluar.
- `tests/Feature/UserApiTest.php`: admin 403; tambah/ubah/nonaktif/set password; tidak bisa
  menurunkan/menonaktifkan diri sendiri; email unik.
- `tests/Feature/InvoiceDeleteTest.php`: admin 403; nomor konfirmasi salah 422; cascade; file
  bersama tetap ada; nomor bisa dipakai lagi.
- `tests/Feature/QtyAdjustTest.php`: admin 403; sebelum scan selesai 409; hanya mengurangi;
  alasan wajib; riwayat; laporan dan detail memakai jumlah keluar.
- Semua tes yang sudah ada diperbarui supaya berjalan sebagai pengguna login (`actingAs`).
- Uji browser: login, tombol superadmin tersembunyi untuk admin, hapus, edit, menu Pengguna.

## Di luar cakupan

Login Google, 2FA, reset password lewat email, hapus pengguna, catatan "siapa scan/cetak",
menu Retur (tetap disembunyikan).

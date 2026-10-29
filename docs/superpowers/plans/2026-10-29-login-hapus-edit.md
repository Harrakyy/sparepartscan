# Login, Hak Akses, Hapus Faktur, Edit Jumlah — Implementation Plan

> Dikerjakan inline (Native) task demi task, TDD per task, satu review menyeluruh di akhir.

**Goal:** Semua halaman/API wajib login; superadmin bisa hapus faktur, edit jumlah keluar, dan kelola pengguna; admin bisa sisanya.

**Architecture:** Auth session bawaan Laravel (tanpa starter kit): `LoginController` + Blade, Gate `superadmin`, middleware `auth` di grup web dan `/api`. `UserController` untuk menu Pengguna. Hapus faktur di `InvoiceService::delete`; edit jumlah di `InvoiceService::adjust` dengan kolom `out_qty` + tabel `qty_adjustments`.

**Tech Stack:** Laravel 12, Blade + ES module, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-29-login-hapus-edit-design.md`

## Global Constraints

- Teks UI/pesan berbahasa Indonesia; amplop API `{success, data, error}` via `ApiResponse`.
- Tidak ada password di kode; akun awal lewat `php artisan app:superadmin`.
- 403 untuk admin di endpoint superadmin: pesan `Hanya superadmin yang boleh melakukan ini`.
- API tanpa login → 401 `Sesi berakhir, silakan masuk lagi`; `api.js` mengarahkan ke `/login`.
- Tes yang sudah ada tetap hijau: `Tests\TestCase` login sebagai superadmin secara default (`$signIn = true`).

## Review Focus

1. Akun dinonaktifkan saat masih login (laptop gudang "ingat saya") harus langsung ditolak di request berikutnya.
2. Admin memanggil `DELETE /api/invoices/{id}` atau `/adjust` langsung (tanpa tombol) → 403.
3. Superadmin terakhir menurunkan/menonaktifkan dirinya → ditolak.
4. Hapus faktur dari laporan bersama tidak menghapus file PDF yang masih dipakai faktur lain.
5. Edit jumlah menaikkan (> qty) atau negatif → 422; laporan memakai `out_qty ?? qty`.

---

### Task 1: Login, peran, wajib login
Files: migration `add_role_to_users`, `User` (role, is_active, last_login_at, `isSuperadmin()`), `UserFactory` (`admin()`, `superadmin()`, `inactive()`), `app/Console/Commands/CreateSuperadmin.php`, `LoginController`, `resources/views/auth/login.blade.php`, `EnsureUserIsActive` middleware, `bootstrap/app.php` (auth untuk web+api, 401 JSON, redirect tamu ke `/login`), `AppServiceProvider` (Gate `superadmin`, `currentUser` ke layout), layout topbar (nama + Keluar), `public/js/api.js` (401 → `/login`), `tests/TestCase.php`, `tests/Feature/AuthTest.php`.
Tests: tamu diarahkan / 401; login benar/salah/nonaktif; remember cookie; throttle 5x; keluar; akun dinonaktifkan saat login → ditolak; perintah `app:superadmin`.
Commit: `feat: login with roles; every page and API requires a signed-in user`

### Task 2: Menu Pengguna
Files: `app/Http/Controllers/Api/UserController.php`, rute `/api/users`, `resources/views/users/index.blade.php`, `public/js/pages/users.js`, menu topbar (superadmin), `tests/Feature/UserApiTest.php`.
Tests: admin 403; tambah (validasi, email unik, password ≥8); ubah peran; nonaktif; set password (remember token diganti); tidak bisa ubah diri sendiri.
Commit: `feat: superadmin user management`

### Task 3: Hapus faktur
Files: `InvoiceService::delete(Invoice, string $confirm)`, `InvoiceController::destroy`, rute `DELETE /api/invoices/{invoice}` (`can:superadmin`), tombol + dialog di `warehouse/session.blade.php` & `warehouse-session.js`, `tests/Feature/InvoiceDeleteTest.php`.
Tests: admin 403; konfirmasi salah 422; cascade (scan, cetak, retur); file bersama tetap ada / file tunggal terhapus; nomor bisa diupload lagi.
Commit: `feat: superadmin can delete an invoice`

### Task 4: Edit jumlah keluar
Files: migration `out_qty` + `qty_adjustments`, model `QtyAdjustment`, `InvoiceService::adjust()`, `detail()` (+ adjustments), `ReportController` (jumlah keluar), struk & tabel sesi (`2 → 1`, mode edit, riwayat), `tests/Feature/QtyAdjustTest.php`.
Tests: admin 403; sebelum scan selesai 409; > qty / < 0 422; alasan wajib; tanpa perubahan 422; riwayat + user; laporan memakai jumlah keluar; edit berulang.
Commit: `feat: superadmin can reduce outgoing quantities after a return`

### Task 5: Uji browser dan review akhir
Login admin vs superadmin, hapus, edit, menu Pengguna; `php artisan test`, `npm test`; satu reviewer untuk seluruh branch; perbaiki Critical/Important.

# Edisi Offline — Sisi App (`sparepartscan`) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Siapkan kode app untuk edisi offline: setting edisi, font lokal, SQLite siap produksi, fitur backup/pemulihan, dan penguncian komputer bertanda tangan — semua di `sparepartscan`, bisa di-TDD.

**Architecture:** Satu kode untuk online & offline, beda lewat `APP_EDITION`. Backup = perintah `php artisan` + `BackupService` + menu superadmin; file SQLite di-`VACUUM INTO`, dicek integritas, rotasi per jam/harian. Lisensi = kunci Ed25519 (publik di app, rahasia hanya di Ashira), dicek offline oleh middleware edisi offline.

**Tech Stack:** Laravel 12, PHP 8.3.36+, SQLite (WAL), ext-sodium, Blade + ES module, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-03-edisi-offline-installer-design.md`

**Catatan lingkup:** Installer (Inno Setup/PowerShell) ada di repo `sparepartsoftware` → rencana terpisah. Rencana ini hanya sisi app.

## Global Constraints

- PHP minimal **8.3.36**; `composer audit` bersih sebelum rilis.
- Teks UI/pesan Bahasa Indonesia; amplop API `{success, data, error}` via `ApiResponse`.
- Fitur backup, menu Backup, rute `/backup/*`, dan pengecekan lisensi **hanya aktif saat `APP_EDITION=offline`** (default `online`); edisi online tak berubah perilaku.
- Backup berisi **database saja**. Rotasi: per jam 48, harian 30; flashdisk & salinan scan 7 harian.
- Kunci rahasia lisensi **tidak pernah** masuk repo/app/installer (`.gitignore`).
- Prasyarat (setup, bukan task): gabung `feat/kunci-login` dan `feat/update-otomatis`-dependent fixes ke branch kerja; semua tes hijau sebelum mulai.

## Review Focus

1. Dua titik scan menulis SQLite bersamaan → tanpa "database is locked" dan tanpa hitungan melebihi qty (Task 3).
2. File `license.lic` disalin dari komputer master ke komputer lain (ID beda) → app menolak (Task 11).
3. Tanda tangan lisензi diubah 1 byte / payload dioprek → ditolak oleh kunci publik (Task 11).
4. `app:backup` jalan saat ada penulisan (scan) → backup tetap utuh (`integrity_check` OK), app tak terkunci (Task 4).
5. `/backup/latest` dipanggil dengan kode pemasangan salah atau beruntun → 403 dan dibatasi (Task 7).

---

### Task 1: Setting edisi + font lokal + bersih-bersih
**Files:** Create `config/edition.php` (atau tambah di `config/app.php`), `app/Support/Edition.php` (helper `Edition::isOffline(): bool` dari `config('app.edition')`), `public/js/dom.js` (ekspor `el()` bersama); Modify `.env`/`.env.example` (`APP_EDITION=online`), `resources/views/layouts/app.blade.php` (Inter dari `public/vendor/inter`, buang Google Fonts), `scripts/copy-vendor.mjs` (+woff2 Inter), 9 file `public/js/pages/*.js` (impor `el` dari `../dom.js`), hapus `tests/Unit/ExampleTest.php`, `composer.json` (skrip `dev` tanpa `npm run dev`, buang `laravel/sail`), buang `parseWholeNumber`/`formatRupiah` di `public/js/format.js` + `tests-js/format.test.js`.
**Interfaces:** Produces: `Edition::isOffline()`; `el(tag, className?, text?)` di `public/js/dom.js`.
- [ ] Step 1: Tulis `tests-js/dom.test.js` — `el('span','x','hi')` → node `<span class="x">hi</span>` (tagName, className, textContent).
- [ ] Step 2: `node --test tests-js/dom.test.js` → FAIL (modul belum ada).
- [ ] Step 3: Buat `public/js/dom.js`; ganti definisi `el()` lokal di tiap `pages/*.js` jadi impor; vendor font Inter + ganti `<link>`; hapus helper format tak terpakai + tesnya; `config/edition.php` + `Edition`.
- [ ] Step 4: `node --test tests-js/**` dan `php artisan test` → PASS; `grep -r googleapis resources` kosong.
- [ ] Step 5: Commit `chore: edition setting, local Inter font, shared el(), ponytail cleanup`.

### Task 2: Gerbang edisi offline
**Files:** Create `tests/Feature/EditionTest.php`; Modify `routes/web.php` & `routes/api.php` (grup rute Backup dibungkus cek offline), `resources/views/layouts/app.blade.php` (menu Backup `@if offline` + superadmin).
**Interfaces:** Consumes `Edition::isOffline()`. Produces: rute `/backup*` & `/api/backups*` hanya terdaftar di offline.
- [ ] Step 1: Tulis tes — `config(['app.edition'=>'online'])` → `GET /api/backups` 404; `offline` + superadmin → 200; admin → 403.
- [ ] Step 2: Jalankan → FAIL.
- [ ] Step 3: Bungkus rute Backup dengan `Edition::isOffline()` + `can:superadmin`; menu Blade kondisional.
- [ ] Step 4: `php artisan test --filter=EditionTest` → PASS.
- [ ] Step 5: Commit `feat: offline edition gate for backup`.

### Task 3: SQLite siap produksi (WAL) + uji beban
**Files:** Create `app/Providers/SqliteTuningProvider.php` (atau boot di `AppServiceProvider`): saat koneksi sqlite → `PRAGMA journal_mode=WAL; busy_timeout=5000; synchronous=NORMAL; foreign_keys=ON`; Create `tests/Feature/SqliteConcurrencyTest.php`.
**Interfaces:** Produces: koneksi sqlite ber-WAL otomatis.
- [ ] Step 1: Tulis tes (koneksi sqlite) — setelah boot, `PRAGMA busy_timeout` = 5000 dan `journal_mode` = wal; dua `scan` cepat pada item qty 1 (satu transaksi lock) → hanya 1 `ok`, 1 `over_qty`, tanpa exception.
- [ ] Step 2: Jalankan → FAIL.
- [ ] Step 3: Pasang PRAGMA via listener koneksi.
- [ ] Step 4: `php artisan test --filter=SqliteConcurrency` → PASS.
- [ ] Step 5: Commit `feat: production SQLite settings (WAL, busy_timeout)`.

### Task 4: Perintah `app:backup`
**Files:** Create `app/Services/BackupService.php`, `app/Console/Commands/BackupDatabase.php` (`app:backup {jenis=manual}` in `perjam|harian|manual`); Modify `routes/console.php` (jadwal `app:backup perjam` tiap jam, `app:backup harian` 23:00 WIB — hanya offline); Create `tests/Feature/BackupTest.php`.
**Interfaces:** Produces: `BackupService::create(string $jenis): ?string` (path file, atau null bila identik) → `backup/{perjam|harian}/faktur-YmdHi.sqlite`; `BackupService::list(): array` (waktu, jenis, ukuran, jumlah_faktur); rotasi 48/30.
- [ ] Step 1: Tulis tes — `create('harian')` hasilkan file yang lulus `PRAGMA integrity_check=ok`; panggil lagi tanpa perubahan data → null & jumlah file tetap 1; tambah faktur lalu `create('harian')` → file ke-2; buat 49 `perjam` → tersisa 48 (terlama terhapus).
- [ ] Step 2: Jalankan → FAIL.
- [ ] Step 3: `VACUUM INTO` temp → `integrity_check` → pindah; dedupe via sha256 vs file terakhir; rotasi per folder.
- [ ] Step 4: `php artisan test --filter=BackupTest` → PASS.
- [ ] Step 5: Commit `feat: app:backup with integrity check, dedupe and rotation`.

### Task 5: Pemulihan
**Files:** Modify `app/Services/BackupService.php` (`restore(string $namaFile): void`), Create `app/Http/Controllers/Api/BackupController.php` (`index`, `restore`), Modify `routes/api.php`; Create `tests/Feature/BackupRestoreTest.php`.
**Interfaces:** Consumes Task 4. Produces: `POST /api/backups/restore {file, confirm}` (confirm harus `PULIHKAN`).
- [ ] Step 1: Tulis tes — buat backup berisi 1 faktur, tambah faktur ke-2, restore ke backup tadi → tinggal 1 faktur; sebelum restore ada backup `sebelum-pulihkan`; confirm salah → 422; admin → 403.
- [ ] Step 2: Jalankan → FAIL.
- [ ] Step 3: `restore`: backup pengaman → tutup koneksi → ganti file db → `migrate --force`.
- [ ] Step 4: `php artisan test --filter=BackupRestore` → PASS.
- [ ] Step 5: Commit `feat: restore a backup (superadmin), safety backup first`.

### Task 6: Backup ke flashdisk
**Files:** Modify `BackupService.php` (`removableDisks(): array`, `copyToDisk(string $drive): array`), `BackupController.php` (`disks`, `toDisk`), `routes/api.php`; Create `tests/Feature/BackupDiskTest.php`.
**Interfaces:** `removableDisks()` → `[{drive, label, free}]` (via PowerShell `Get-Volume DriveType=Removable`; di tes di-fake lewat binding/contract); `copyToDisk($drive)` salin backup terbaru + 7 harian ke `<drive>:\VerifikasiFaktur-Backup\`, hapus >7 hari.
- [ ] Step 1: Tulis tes (disk sumber di-fake ke folder temp) — `copyToDisk` menyalin ≤8 file; file >7 hari di tujuan terhapus; `POST /api/backups/to-disk` tanpa disk → 422 pesan "Tidak ada flashdisk".
- [ ] Step 2: Jalankan → FAIL.
- [ ] Step 3: Implement; sumber disk lewat kontrak agar bisa di-fake di tes; simpan "terakhir ke flashdisk".
- [ ] Step 4: `php artisan test --filter=BackupDisk` → PASS.
- [ ] Step 5: Commit `feat: back up to a chosen flash drive, keep 7 days`.

### Task 7: Endpoint salinan untuk komputer scan
**Files:** Modify `BackupController.php` (`latest`), `routes/web.php` (di luar `auth`), Create `tests/Feature/BackupLatestTest.php`.
**Interfaces:** `GET /backup/latest` header `X-Pairing-Code` = kode pemasangan (`config('app.pairing_code')`) → unduh backup terbaru; salah/absen → 403; batas 10/menit.
- [ ] Step 1: Tulis tes — kode benar → 200 + file; kode salah → 403; 11× cepat → 429.
- [ ] Step 2: Jalankan → FAIL.
- [ ] Step 3: Implement (bandingkan kode dgn `hash_equals`, `throttle`).
- [ ] Step 4: `php artisan test --filter=BackupLatest` → PASS.
- [ ] Step 5: Commit `feat: pairing-code-guarded /backup/latest for scan copies`.

### Task 8: Menu Backup (UI)
**Files:** Create `resources/views/backup/index.blade.php`, `public/js/pages/backup.js`; Modify `routes/web.php` (`/backup`, offline+superadmin), layout menu.
**Interfaces:** Consumes Task 5–6. Memakai `el()` (Task 1), `apiGet/apiPost`.
- [ ] Step 1: `node --check` + render: daftar versi (waktu/jenis/jumlah), tombol Pulihkan (konfirmasi ketik `PULIHKAN`), daftar flashdisk + tombol Backup ke flashdisk, "terakhir ke flashdisk".
- [ ] Step 2: Uji di browser server tes (edisi offline, superadmin): buat backup, pulihkan, tampil disk.
- [ ] Step 3: Commit `feat: Backup menu for superadmin (offline)`.

### Task 9: Kunci lisensi + ID komputer
**Files:** Create `app/Services/License/Keys.php` (`generate()`, path kunci di `storage/app/keys/`), `app/Console/Commands/LicenseKeygen.php` (`license:keygen`), `app/Services/License/MachineId.php` (`current(): string` dari sumber hardware yang di-inject; hash), `app/Console/Commands/LicenseId.php` (`license:id`); Modify `.gitignore` (`storage/app/keys/secret.key`, `storage/app/license.lic`); Create `tests/Feature/LicenseKeysTest.php`.
**Interfaces:** Produces: `Keys::sign(string $msg): string`, `Keys::verify(string $msg,string $sig): bool` (Ed25519 via sodium); `MachineId::current(): string`; kunci publik ikut repo, rahasia tidak.
- [ ] Step 1: Tulis tes — `license:keygen` buat sepasang kunci; `sign`/`verify` cocok; `verify` tolak msg yang diubah; `keygen` menolak bila kunci sudah ada; `MachineId::current()` dengan sumber fake stabil → hash sama tiap panggil.
- [ ] Step 2: Jalankan → FAIL.
- [ ] Step 3: Implement sodium; `MachineId` ambil nilai dari kontrak sumber (di produksi: WMIC/PowerShell UUID+serial).
- [ ] Step 4: `php artisan test --filter=LicenseKeys` → PASS.
- [ ] Step 5: Commit `feat: Ed25519 license keys and machine id`.

### Task 10: Pembuat lisensi (sisi Ashira)
**Files:** Create `app/Console/Commands/LicenseBuat.php` (`license:buat {machine_id} --untuk= --sampai=`), `app/Services/License/LicenseFile.php` (`issue(array): string`, `parse(string): array`, `valid(array,string machineId,CarbonInterface now): array{ok,reason}`); Create `tests/Feature/LicenseIssueTest.php`.
**Interfaces:** Consumes Task 9. Produces format `license.lic` (JSON payload + `signature`); `valid()` cek tanda tangan + `machine_id` + `expires_at`.
- [ ] Step 1: Tulis tes — `license:buat ABC --sampai=2026-12-31` tulis file; `valid()` true utk machineId ABC sebelum tanggal; machineId beda → `{ok:false, reason:'machine'}`; lewat tanggal → `reason:'expired'`; signature diubah → `reason:'signature'`; `license:buat` tanpa kunci rahasia → gagal.
- [ ] Step 2: Jalankan → FAIL.
- [ ] Step 3: Implement issue/parse/valid; toleransi machine id (≥N dari M nilai) via `MachineId`.
- [ ] Step 4: `php artisan test --filter=LicenseIssue` → PASS.
- [ ] Step 5: Commit `feat: license:buat signs offline licenses`.

### Task 11: Penegakan lisensi + halaman aktivasi
**Files:** Create `app/Http/Middleware/EnsureLicensed.php`, `resources/views/license/activate.blade.php`, `app/Http/Controllers/LicenseController.php` (`show`, `store`); Modify `bootstrap/app.php` (pasang middleware di web, hanya offline, setelah `auth`? — sebelum, agar aktivasi tak perlu login), `routes/web.php` (`/aktivasi`); Create `tests/Feature/LicenseEnforceTest.php`.
**Interfaces:** Consumes Task 9–10. `/aktivasi` selalu terbuka saat offline; rute lain diblokir → redirect `/aktivasi` bila lisensi tidak sah; hasil `valid()` di-cache ~5 menit.
- [ ] Step 1: Tulis tes — offline tanpa `license.lic` → `GET /gudang` redirect `/aktivasi`; unggah lisensi sah (machineId cocok) → `/gudang` 200; lisensi machineId beda → tetap `/aktivasi`; **online → tak pernah cek** (`/gudang` jalan tanpa lisensi).
- [ ] Step 2: Jalankan → FAIL.
- [ ] Step 3: Middleware baca+validasi+cache; halaman aktivasi tampil `MachineId::current()` + form unggah/tempel; simpan ke `storage/app/license.lic`.
- [ ] Step 4: `php artisan test --filter=LicenseEnforce` dan `php artisan test` → PASS.
- [ ] Step 5: Commit `feat: license enforcement and activation page (offline only)`.

### Task 12: Review akhir
- [ ] `php artisan test` + `npm test` hijau; `composer audit` bersih.
- [ ] Satu reviewer menyeluruh (fresh) terhadap seluruh diff; perbaiki Critical/Important.

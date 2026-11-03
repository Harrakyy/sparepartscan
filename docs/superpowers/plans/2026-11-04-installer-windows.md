# Installer Windows (`sparepartsoftware`) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Satu `Verifikasi-Faktur-Setup-<versi>.exe` yang memasang komputer master (PHP + Apache + app + SQLite, layanan, jadwal, firewall) atau komputer scan (ikon + tarik backup per jam), tanpa internet saat pemasangan.

**Architecture:** Repo baru `C:\KERJA\ASHIRATECH\sparepartsoftware` (remote `https://github.com/Harrakyy/sparepartsoftware.git`, tidak di-push dalam plan ini). `build.ps1` mengunduh PHP/Apache/VC++ versi tetap (cek SHA-256), mengambil app lewat `git archive` dari repo `sparepart`, `composer install --no-dev`, lalu Inno Setup mengompilasi `.iss`. Semua kerja sistem (layanan, firewall, jadwal, `.env`) ada di skrip PowerShell yang dipanggil Inno setelah file tersalin; logika murninya di modul `.psm1` yang diuji Pester. Data persisten hidup di luar `app\` (`data\`, `backup\`) supaya update cukup mengganti `app\`.

**Tech Stack:** PowerShell 5.1 + Pester 3.4 (bawaan Windows), Inno Setup 6, PHP 8.3.36 x64 TS, Apache 2.4 (Apache Lounge, VS18), Laravel 12 app `sparepart`.

**Spec:** `docs/superpowers/specs/2026-11-03-edisi-offline-installer-design.md` (Fase 3)

## Global Constraints

- PHP minimal **8.3.36**; `build.ps1` menolak build bila PHP di paket di bawah itu atau `composer audit` tidak bersih.
- Pemasangan tanpa internet, Laragon, Composer, atau npm. Master: Windows 11; scan: Windows 10/11.
- Folder pasang `C:\VerifikasiFaktur\` → `app\`, `php\`, `apache\`, `data\` (`database.sqlite`, `storage\`, `.env`), `backup\`.
- Port 80; bila terpakai, 8080. Aturan firewall profil **Private**; peringatan bila jaringan berstatus Public.
- `schedule:run` tiap menit lewat Task Scheduler, akun SYSTEM.
- Salinan di komputer scan: 24 per jam + 7 harian, dari `GET /backup/latest` header `X-Pairing-Code`.
- Ikon desktop membuka Microsoft Edge mode aplikasi (`msedge.exe --app=<alamat>`).
- `secret.key` dan `license.lic` **tidak pernah** masuk paket; build menolak bila ada di stage.
- Nama hasil: `dist\Verifikasi-Faktur-Setup-<versi>.exe`.
- Update: data, `.env` (APP_KEY, kode pemasangan), dan database tetap; backup `sebelum-update` dulu.

## Review Focus

1. Update/uninstall menghapus `app\` sementara `app\storage` adalah junction ke `data\storage` → PDF faktur & `license.lic` ikut terhapus. Harus: junction dilepas dulu, isi `data\storage` utuh (Task 3).
2. Port 80 dipakai (IIS/Skype/Laragon) → pakai 8080, dan `APP_URL`, `Listen`, firewall, ikon, info pemasangan memakai port yang sama; update memakai port lama (Task 3).
3. Setup dijalankan ulang/update → `APP_KEY` dan kode pemasangan **tidak** dibuat ulang (Task 3).
4. Komputer scan: master mati / kode salah / belum ada backup → pesan jelas; tarik backup gagal tidak menghapus salinan lama; rotasi 24/7 (Task 5).
5. Jaringan berstatus Public atau IP dari DHCP → info pemasangan memuat peringatan (Task 3).

---

### Task 1: Pendukung installer di app (`sparepart`)

**Files:**
- Modify: `app/Console/Commands/CreateSuperadmin.php`, `app/Services/BackupService.php` (`JENIS`), `config/backup.php` (`keep`)
- Test: `tests/Feature/CreateSuperadminTest.php` (buat bila belum ada), `tests/Feature/BackupTest.php`

**Interfaces:**
- Produces: `php artisan app:superadmin {email?} {--name=Superadmin} {--reset}` (tanpa `email` → ditanyakan di konsol, supaya ikon "Reset superadmin" tak perlu tahu email); `php artisan app:backup sebelum-update`.

- [ ] **Step 1: Tulis tes gagal**
  - `test_reset_sets_a_new_password_and_unlocks`: superadmin ada dengan `is_active=false`, `failed_logins=9`, `locked_until` besok → `artisan('app:superadmin', ['email'=>…, '--reset'=>true])->expectsQuestion('Password (min. 8 karakter)','baru12345')->expectsQuestion('Ulangi password','baru12345')->assertSuccessful()`; lalu `Hash::check('baru12345', $u->password)`, `is_active` true, `failed_logins` 0, `locked_until` null.
  - `test_reset_refuses_an_unknown_or_non_superadmin_email` → `assertFailed()`.
  - `test_backup_accepts_sebelum_update`: `app(BackupService::class)->create('sebelum-update')` mengembalikan path di folder `sebelum-update`.
- [ ] **Step 2:** `php artisan test --filter="CreateSuperadmin|BackupTest"` → FAIL.
- [ ] **Step 3:** `--reset` mencari user superadmin berdasarkan email (bukan validasi `unique`), set password + `is_active=true` + `failed_logins=0` + `locked_until=null` + `last_failed_login_at=null`, akhiri sesinya seperti ganti password biasa bila app punya helper itu. Tambah `'sebelum-update'` ke `JENIS` dan `keep.sebelum-update = 10`.
- [ ] **Step 4:** `php artisan test` → semua PASS.
- [ ] **Step 5:** Commit `feat: superadmin reset and pre-update backups for the installer`.

### Task 2: Repo + build (unduh, verifikasi, stage)

**Files:**
- Create (di `sparepartsoftware`): `.gitignore` (`cache/`, `stage/`, `dist/`), `README.md`, `versions.psd1`, `build.ps1`, `build/Build.psm1`, `templates/php.ini`, `templates/httpd.conf`, `tests/Build.Tests.ps1`

**Interfaces:**
- Produces: `versions.psd1` = `@{ AppVersion='1.0.0'; Php=@{Version;Url;Sha256}; Apache=@{Version;Url;Sha256}; VcRedist=@{Url;Sha256}; MinPhp='8.3.36' }`.
- `Get-VerifiedFile -Url -Sha256 -CacheDir` → path; lempar galat berisi kata `checksum` bila hash beda (dan hapus file rusak).
- `Assert-CleanStage -StageDir` → lempar galat bila ada `secret.key` atau `license.lic` di bawahnya.
- `Test-PhpVersion -Actual -Minimum` → bool (perbandingan `[version]`).
- Stage hasil: `stage\app` (tanpa folder `storage`), `stage\storage-template`, `stage\php` (+ `php.ini`), `stage\apache\Apache24`, `stage\vc_redist.x64.exe`.

- [ ] **Step 1:** `git init`, remote, `.gitignore`. Isi `versions.psd1` dengan URL + SHA-256 resmi (PHP dari `windows.php.net/downloads/releases/` dengan cadangan `/archives/`; SHA dari `sha256sum.txt` situs itu; Apache Lounge `httpd-2.4.66-260223-Win64-VS18.zip` + SHA-nya; VC++ `aka.ms/vs/17/release/vc_redist.x64.exe` + SHA saat ini).
- [ ] **Step 2: Tes Pester gagal** (`tests/Build.Tests.ps1`): checksum salah → galat `checksum` dan file tidak tertinggal; `Assert-CleanStage` pada folder berisi `x\storage\app\keys\secret.key` → galat; folder bersih → tidak galat; `Test-PhpVersion '8.3.35' '8.3.36'` → `$false`, `'8.3.36'` → `$true`, `'8.4.0'` → `$true`.
- [ ] **Step 3:** `Invoke-Pester .\tests\Build.Tests.ps1` → FAIL.
- [ ] **Step 4:** Implement `Build.psm1` dan `build.ps1 [-AppRepo ..\sparepart] [-AppRef v<AppVersion>] [-SkipInno]`: unduh+verifikasi → ekstrak → `git -C $AppRepo archive $AppRef` ke `stage\app` → pindah `storage\` ke `stage\storage-template` → `composer install --no-dev --optimize-autoloader --no-interaction` + `composer audit` (gagal = stop) → `php -r "echo PHP_VERSION;"` dari `stage\php` lewat `Test-PhpVersion` → salin `templates\php.ini` (ext: curl, fileinfo, mbstring, openssl, pdo_sqlite, sqlite3, sodium, zip; opcache on; `upload_max_filesize=20M`, `post_max_size=25M`; `date.timezone=Asia/Jakarta`; `extension_dir` relatif `ext`) → `Assert-CleanStage` → (kecuali `-SkipInno`) ISCC (Task 6).
- [ ] **Step 5:** Pester PASS; `.\build.ps1 -AppRef HEAD -SkipInno` selesai dan `stage\php\php.exe -v` = 8.3.36, `stage\app\vendor\autoload.php` ada, `stage\app\storage` tidak ada.
- [ ] **Step 6:** Commit `feat: reproducible build stage with checksum and PHP version gates`.

### Task 3: Modul master (logika murni)

**Files:**
- Create: `scripts/Master.psm1`, `tests/Master.Tests.ps1`

**Interfaces:**
- `Get-AppPort -Previous <int?> -IsListening <scriptblock>` → `int`: `Previous` bila ada; kalau tidak 80 bila bebas, selain itu 8080.
- `New-EnvContent -Existing <string?> -DataDir -BackupDir -Url` → `string`: bila `Existing` punya `APP_KEY`/`APP_PAIRING_CODE`, nilainya dipertahankan; selain itu `APP_KEY=base64:<32 byte acak>` dan kode 8 karakter dari `ABCDEFGHJKMNPQRSTUVWXYZ23456789`. Selalu: `APP_ENV=production`, `APP_DEBUG=false`, `APP_EDITION=offline`, `DB_CONNECTION=sqlite`, `DB_DATABASE=<DataDir>\database.sqlite`, `BACKUP_DIR=<BackupDir>`, `APP_URL=<Url>`, `SESSION_DRIVER=file`, `CACHE_STORE=file`; tanpa `LICENSE_ENFORCE`.
- `Set-StorageLink -AppDir -DataDir -TemplateDir` → salin template ke `data\storage` bila belum ada, selalu timpa `data\storage\app\keys\public.key` dari template, buat junction `app\storage` → `data\storage`.
- `Remove-AppDir -AppDir` → lepas junction `app\storage` (tanpa menelusuri isinya) lalu hapus `app\`.
- `Get-InstallInfo -Url -PairingCode -LanIp -NetworkCategory -Dhcp` → teks info pemasangan; berisi peringatan bila `NetworkCategory -eq 'Public'` dan bila `Dhcp`.
- `Write-HttpdConf -Template -Out -ServerRoot -AppPublic -PhpDir -Port`.

- [ ] **Step 1: Tes Pester gagal**: port (Previous 8080 → 8080; 80 bebas → 80; 80 terpakai → 8080); env baru punya `APP_KEY=base64:` 44 karakter dan kode 8 karakter dari alfabet itu; env dengan `Existing` mempertahankan keduanya persis; **junction**: buat `data\storage\app\private\faktur.pdf`, `Set-StorageLink`, lalu `Remove-AppDir` → `app\` hilang, `faktur.pdf` masih ada; public.key di data ditimpa dari template; info memuat `Public` → kata `PERINGATAN` dan alamat `http://<ip>:<port>`; httpd.conf hasil berisi `Listen <port>` dan `DocumentRoot` app\public.
- [ ] **Step 2:** `Invoke-Pester .\tests\Master.Tests.ps1` → FAIL.
- [ ] **Step 3:** Implement. Junction: `cmd /c mklink /J`; lepas dengan `cmd /c rmdir "<app>\storage"` (bukan `Remove-Item -Recurse`); setelah lepas, pastikan `app\storage` tidak ada sebelum menghapus `app\`. `templates\httpd.conf`: `ServerRoot`, `Listen`, `LoadModule php_module "<php>\php8apache2_4.dll"`, `PHPIniDir`, `DocumentRoot` + `AllowOverride All`, `mod_rewrite`.
- [ ] **Step 4:** Pester PASS.
- [ ] **Step 5:** Commit `feat: master install helpers (port, env, storage junction, info)`.

### Task 4: Skrip master (pasang, update, copot)

**Files:**
- Create: `scripts/setup-master.ps1`, `scripts/pre-update.ps1`, `scripts/uninstall-master.ps1`

**Interfaces:**
- Consumes: Task 3; Task 1 (`app:backup sebelum-update`).
- `setup-master.ps1 -InstallDir [-RestoreFrom <file>]` → exit 0 + tulis `C:\VerifikasiFaktur\INFO-PEMASANGAN.txt` dan `data\install.json` (`{port, url, pairingCode}`); log ke `data\install.log`; exit ≠ 0 bila gagal.
- `pre-update.ps1 -InstallDir` → hentikan layanan `VerifikasiFaktur`, `app:backup sebelum-update`, `Remove-AppDir`. Tidak melakukan apa pun bila belum terpasang.
- `uninstall-master.ps1 -InstallDir` → hapus layanan, tugas jadwal, aturan firewall, ikon; `data\` dan `backup\` tetap.

- [ ] **Step 1:** `setup-master.ps1` berurutan: VC++ redist `/install /quiet /norestart` → port (`Get-AppPort` dengan `install.json` lama) → `data\.env` (`New-EnvContent`) disalin ke `app\.env` → `Set-StorageLink` → `-RestoreFrom` (cek `PRAGMA integrity_check`=`ok` lewat php, lalu salin jadi `database.sqlite`; gagal = stop) → buat `database.sqlite` bila belum ada → `artisan migrate --force` → `artisan optimize` → `Write-HttpdConf` → `httpd -k install -n VerifikasiFaktur` (bila belum) + `Start-Service` → firewall `New-NetFirewallRule -DisplayName 'Verifikasi Faktur' -LocalPort <port> -Profile Private` (hapus yang lama dulu) → `schtasks /Create /TN "VerifikasiFaktur\Jadwal" /SC MINUTE /MO 1 /RU SYSTEM /F` → ikon Public Desktop "Verifikasi Faktur" (Edge `--app=http://localhost:<port>`) dan "Reset superadmin" (`cmd /k` php artisan app:superadmin --reset, jalankan sebagai admin; email ditanyakan) → info pemasangan.
- [ ] **Step 2:** `pre-update.ps1` dan `uninstall-master.ps1` sesuai Interfaces.
- [ ] **Step 3:** Verifikasi kering: `powershell -File scripts\setup-master.ps1 -?` tanpa galat parse; `Invoke-Pester` semua tes tetap PASS. (Pemasangan nyata diuji di Task 7.)
- [ ] **Step 4:** Commit `feat: master setup, pre-update and uninstall scripts`.

### Task 5: Peran scan

**Files:**
- Create: `scripts/Scan.psm1`, `scripts/setup-scan.ps1`, `scripts/pull-backup.ps1`, `tests/Scan.Tests.ps1`

**Interfaces:**
- `Test-MasterConnection -Server -PairingCode -Get <scriptblock>` → `@{ ok=bool; message=string }`: `/up` gagal → "Tidak bisa terhubung ke komputer master di <server>. Pastikan satu WiFi dan master menyala."; `/backup/latest` 403 → "Kode pemasangan salah."; 200 atau 404 → ok.
- `Invoke-BackupPull -Server -PairingCode -BackupDir -Download <scriptblock> -Now <datetime>` → unduh ke file sementara; gagal → keluar tanpa menyentuh salinan lama; isi sama dengan per-jam terakhir (SHA-256) → buang; selain itu simpan `perjam\faktur-yyyyMMdd-HHmm.sqlite`, bila belum ada harian hari ini salin ke `harian\`; rotasi perjam 24, harian 7.
- `setup-scan.ps1 -InstallDir -Server -PairingCode` → exit ≠ 0 dengan pesan bila koneksi gagal; tulis `scan.json` (izin baca hanya Administrators+SYSTEM), ikon Edge `--app=<server>`, tugas `VerifikasiFaktur\TarikBackup` tiap jam (SYSTEM) menjalankan `pull-backup.ps1`.

- [ ] **Step 1: Tes Pester gagal**: tiga cabang `Test-MasterConnection` dengan `-Get` palsu; pull sukses membuat file perjam + harian; pull kedua dengan isi sama tidak menambah file; 30 pull berbeda (Now maju 1 jam) → perjam 24, harian ≤ 7 (Now maju 1 hari); `-Download` yang melempar galat → jumlah file tetap.
- [ ] **Step 2:** `Invoke-Pester .\tests\Scan.Tests.ps1` → FAIL.
- [ ] **Step 3:** Implement (`-Get`/`-Download` default memakai `Invoke-WebRequest -UseBasicParsing -TimeoutSec 15`).
- [ ] **Step 4:** Pester PASS.
- [ ] **Step 5:** Commit `feat: scan role setup and hourly backup pull`.

### Task 6: Installer Inno Setup

**Files:**
- Create: `installer/VerifikasiFaktur.iss`; Modify: `build.ps1` (panggil ISCC), `README.md`

**Interfaces:**
- Consumes: stage (Task 2), skrip (Task 4–5).
- Parameter senyap: `/ROLE=master|scan`, `/SERVER=`, `/CODE=`, `/ADMINEMAIL=`, `/ADMINNAME=`, `/SKIPADMIN`.

- [ ] **Step 1:** `.iss`: `AppId` GUID tetap, `DefaultDirName=C:\VerifikasiFaktur`, `DisableDirPage=yes`, `PrivilegesRequired=admin`, `ArchitecturesInstallIn64BitMode=x64compatible`, `MinVersion=10.0`, `OutputBaseFilename=Verifikasi-Faktur-Setup-{#AppVersion}`, bahasa Indonesia. Halaman: Peran (master/scan); master pasang baru: nama+email superadmin dan pilihan file backup untuk dipulihkan (opsional); scan: alamat master + kode pemasangan (cek lewat `setup-scan.ps1` sebelum lanjut). Files master: `stage\app`, `stage\php`, `stage\apache`, `stage\storage-template`, VC++ ke `{tmp}`; skrip untuk kedua peran. `PrepareToInstall`: master + sudah terpasang → `pre-update.ps1`. `CurStepChanged(ssPostInstall)`: jalankan `setup-master.ps1`/`setup-scan.ps1`; exit ≠ 0 → pesan + lokasi log. Master pasang baru tanpa `/SKIPADMIN` → buka konsol `app:superadmin <email> --name=<nama>` (password diketik di konsol). Halaman akhir: buka `INFO-PEMASANGAN.txt` dan Edge ke `http://localhost:<port>/aktivasi`. `[UninstallRun]`: `uninstall-master.ps1`.
- [ ] **Step 2:** `build.ps1` juga menulis `dist\RELEASE-NOTES.txt` (versi app + commit, PHP, Apache, tanggal build). `.\build.ps1 -AppRef HEAD` → `dist\Verifikasi-Faktur-Setup-1.0.0.exe` ada (> 30 MB), catatan rilis memuat `8.3.36`, ISCC tanpa galat.
- [ ] **Step 3:** README: prasyarat build, `.\build.ps1`, rilis (tag `v<versi>` di `sparepart`), **backup `secret.key`**, cara `license:buat`.
- [ ] **Step 4:** Commit `feat: Inno Setup installer with master and scan roles`.

### Task 7: Uji pasang di laptop ini

**Files:** Modify: `README.md` (catatan hasil uji)

- [ ] **Step 1:** Master senyap: `Setup.exe /VERYSILENT /ROLE=master /SKIPADMIN` → exit 0; `Get-Service VerifikasiFaktur` Running; `GET http://localhost:<port>/up` 200; `/gudang` redirect `/aktivasi`; `schtasks /Query /TN VerifikasiFaktur\Jadwal` ada.
- [ ] **Step 2:** Aktivasi: ID dari `/aktivasi` → `php artisan license:buat <id> --untuk=Uji --sampai=2027-12-31` (di repo `sparepart`) → unggah di `/aktivasi` → `/login` tampil. Buat superadmin lewat `app:superadmin`, masuk, upload faktur contoh, buat backup.
- [ ] **Step 3:** Scan ke master sendiri: `Setup.exe /VERYSILENT /ROLE=scan /SERVER=http://localhost:<port> /CODE=<kode>` di folder uji terpisah → jalankan tugas `TarikBackup` → file muncul di `perjam\`.
- [ ] **Step 4:** Update: build ulang, pasang lagi master → data, lisensi, dan faktur tetap; backup `sebelum-update` ada.
- [ ] **Step 5:** Copot → layanan/tugas/firewall hilang, `data\` dan `backup\` tetap.
- [ ] **Step 6:** Commit `docs: local install test results`.

## Di luar plan ini

Halaman "Tentang" berisi versi (versi baru dicatat di `dist\RELEASE-NOTES.txt`), peringatan flashdisk > 7 hari di app, panduan per peran (Fase 5), uji beban dua klien (skrip terpisah sebelum uji coba Fase 4), push repo ke GitHub.

# Mode kabel, akun tanpa repot, dan alat lisensi — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** MGI dipasang lewat kabel LAN ber-alamat tetap, akun dibuat lewat web (username), petugas mendaftar sendiri, dan lisensi dibuat lewat form lokal — dengan pemeriksaan komputer sebelum apa pun dipasang.

**Architecture:** App `sparepart` (branch `feat/installer`): username menggantikan email, dua halaman tamu baru khusus edisi offline (`/akun-pertama`, `/daftar`) dan tabel `settings`; alat lisensi di `tools/` (tidak ikut paket). Installer `sparepartsoftware` (main): logika murni kabel/pemeriksaan di `scripts/Cable.psm1` (diuji Pester dengan data adaptor palsu), penerapannya di skrip setup/uninstall, halaman Inno baru memanggil skrip pemeriksaan.

**Tech Stack:** Laravel 12 / PHP 8.3 / PHPUnit; PowerShell 5.1 + Pester 3.4; Inno Setup 6.

**Spec:** `docs/superpowers/specs/2026-11-04-kabel-akun-lisensi-design.md` (melanjutkan `2026-11-03-edisi-offline-installer-design.md`). Plan ini menggantikan Task 7 yang belum selesai di `2026-11-04-installer-windows.md`.

## Global Constraints

- Username: `^[a-z0-9._-]{3,30}$`, disimpan huruf kecil, unik; tidak ada email di form akun.
- `/akun-pertama` dan `/daftar` hanya edisi offline; edisi online tetap 404.
- Akun pertama hanya selama `users` kosong; daftar hanya bila `allow_registration` = `1` (default `1`); `throttle:5,1`; akun daftar = role `admin`.
- Subnet kabel `192.168.77.0/24`: master `.1`, scan titik 1 `.2`, titik 2 `.3`; prefix 24; tanpa gateway & DNS.
- Firewall kabel: `DisplayName 'Verifikasi Faktur (kabel)'`, TCP port app, `RemoteAddress 192.168.77.0/24`, `-Profile Any`.
- Satu Setup.exe: `ArchitecturesAllowed=x86compatible x64compatible`; master hanya 64-bit.
- Lisensi dari form: tanpa `expires_at` (selamanya); `secret.key` tidak pernah ke paket.
- Pesan yang tampil ke pengguna berbahasa Indonesia.

## Review Focus

1. Migrasi email→username pada data yang ada: dua email dengan bagian depan sama (`budi@a.com`, `budi@b.com`) → `budi`, `budi-2`; email aneh (`b@x`) → `user<id>` (Task 1).
2. `/akun-pertama` dikirim dua kali cepat (dua tab) → hanya satu superadmin; permintaan kedua 404 (Task 2).
3. Komputer master yang port LAN-nya tersambung router kantor → pemeriksaan ⛔, alamat tidak diubah (Task 6).
4. Titik scan yang alamatnya sudah dipakai PC lain → ⛔ sebelum mengubah apa pun; setelah ⛔, adaptor kembali ke konfigurasi semula (Task 6–7).
5. Copot aplikasi → port LAN kembali DHCP persis seperti sebelum dipasang (Task 7).

---

### Task 1: Login & akun pakai username (app)

**Files:**
- Create: `database/migrations/2026_11_04_000001_replace_email_with_username.php`
- Modify: `app/Models/User.php`, `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Controllers/Api/UserController.php`, `database/factories/UserFactory.php`, `app/Console/Commands/CreateSuperadmin.php`, `resources/views/auth/login.blade.php`, `resources/views/users/index.blade.php`, `resources/views/layouts/app.blade.php`, `public/js/pages/users.js`, `bootstrap/app.php`
- Test: `tests/Feature/UsernameMigrationTest.php` (baru); ubah `AuthTest`, `LoginLockoutTest`, `UserApiTest`, `CreateSuperadminTest`

**Interfaces:**
- Produces: `users.username` (string 30, unique); `User::USERNAME_RULE = 'regex:/^[a-z0-9._-]{3,30}$/'`; `User::normalizeUsername(string): string` (trim + lowercase); `app:superadmin {username?} {--name=Superadmin} {--reset}`.

- [ ] **Step 1: Tes gagal**
  - `UsernameMigrationTest::test_emails_become_unique_usernames`: `$m = require base_path('database/migrations/2026_11_04_000001_replace_email_with_username.php'); $m->down();` → sisipkan baris `email` `Budi@a.com`, `budi@b.com`, `b@x`, `ana.k_1@x.com` → `$m->up();` → username `budi`, `budi-2`, `user<id>`, `ana.k_1`; kolom `email` tidak ada. (Tidak memakai `migrate:rollback --step`, supaya tetap benar setelah Task 3 menambah migrasi.)
  - `AuthTest`: login dengan `username` + password → redirect `/gudang`; `Budi` (huruf besar) tetap masuk; username salah → error `Username atau password salah` pada field `username`.
  - `UserApiTest`: `POST /api/users` dengan `username`; username `ab` / `Budi Santoso` / duplikat → 422 dengan pesan `Username 3–30 karakter: huruf kecil, angka, titik, minus, garis bawah` atau `Username sudah dipakai`.
  - `CreateSuperadminTest`: ganti argumen email jadi username; tambah `test_permission_error_tells_to_run_as_administrator` (bind `DB` ke koneksi yang melempar `PDOException('unable to open database file')` → output memuat `Jalankan sebagai administrator`).
- [ ] **Step 2:** `php artisan test` → FAIL pada tes di atas.
- [ ] **Step 3:** Implement. Migrasi `up()` memakai algoritma Review Focus #1 (iterasi berurut `id`); `down()` mengembalikan `email` = `<username>@lokal.invalid`. Login & UserController memvalidasi `username` (`required`, `USERNAME_RULE`, unik di create) setelah `normalizeUsername`. Tabel Pengguna menampilkan kolom Username. Handler konsol di `bootstrap/app.php`: `PDOException`/`ErrorException` berisi `unable to open database file`, `Permission denied`, atau `does not exist` saat `runningInConsole()` di Windows → pesan admin.
- [ ] **Step 4:** `php artisan test` → semua PASS; `npm test` → PASS.
- [ ] **Step 5:** Commit `feat: log in with a username instead of an email`.

### Task 2: Halaman akun pertama (app)

**Files:**
- Create: `app/Http/Controllers/Auth/FirstAccountController.php`, `resources/views/auth/first-account.blade.php`, `tests/Feature/FirstAccountTest.php`
- Modify: `routes/web.php`, `app/Http/Controllers/Auth/LoginController.php` (`show`), `app/Http/Controllers/LicenseController.php` (`store`)

**Interfaces:**
- Consumes: Task 1 username.
- Produces: rute `GET/POST /akun-pertama` (nama `akun-pertama`), middleware `edition.offline`, di luar `auth`/`guest`.

- [ ] **Step 1: Tes gagal** (`FirstAccountTest`, `DatabaseMigrations`, `$signIn = false`, edisi offline):
  - kosong → `GET /akun-pertama` 200; `GET /login` redirect `/akun-pertama`.
  - `POST` nama `Bos`, username `bos`, password+ulangi `rahasia123` → user superadmin aktif, terautentikasi, redirect `/gudang`.
  - setelah ada akun → `GET` dan `POST /akun-pertama` 404 (Review Focus #2: dua POST berurutan → `User::count() === 1`, kedua 404).
  - password ≠ ulangi → error `Password tidak sama`; < 8 → `Password minimal 8 karakter`.
  - edisi online → 404.
  - `LicenseController`: aktivasi sah tanpa user → redirect `/akun-pertama`; dengan user → `/login`.
- [ ] **Step 2:** `php artisan test --filter=FirstAccount` → FAIL.
- [ ] **Step 3:** Implement; POST di dalam `DB::transaction`: cek kosong, buat user, lalu bila `User::count() > 1` → batalkan transaksi + 404 (SQLite menulis berurutan, jadi permintaan kedua melihat akun pertama; `lockForUpdate` tidak berlaku di SQLite). View mengikuti gaya `auth/login.blade.php`.
- [ ] **Step 4:** `php artisan test` → PASS.
- [ ] **Step 5:** Commit `feat: create the first superadmin from a web page (offline)`.

### Task 3: Pendaftaran petugas + sakelar (app)

**Files:**
- Create: migrasi `2026_11_04_000002_create_settings_table.php`, `app/Models/Setting.php`, `app/Http/Controllers/Auth/RegisterController.php`, `app/Http/Controllers/Api/SettingController.php`, `resources/views/auth/register.blade.php`, `tests/Feature/RegisterTest.php`
- Modify: `routes/web.php`, `routes/api.php`, `resources/views/auth/login.blade.php` (tombol Daftar), `resources/views/users/index.blade.php` + `public/js/pages/users.js` (sakelar)

**Interfaces:**
- Produces: `Setting::get(string $key, ?string $default = null): ?string`, `Setting::set(string $key, string $value): void`; kunci `allow_registration` (default `'1'` bila tak ada baris); `PATCH /api/settings` `{allow_registration: bool}` (superadmin, `edition.offline`) → `{allow_registration: bool}`; `GET /api/settings` sama.

- [ ] **Step 1: Tes gagal** (`RegisterTest`, edisi offline, sudah ada 1 superadmin):
  - `GET /login` memuat tautan `/daftar`; `POST /daftar` (`Budi`, `budi`, `rahasia123`×2) → user role `admin` aktif, login, redirect `/gudang`.
  - sakelar mati (`Setting::set('allow_registration','0')`) → `GET/POST /daftar` 404 dan login tanpa tautan Daftar.
  - username sudah ada → `Username sudah dipakai`.
  - 6 POST/menit dari IP sama → yang ke-6 429.
  - edisi online → 404.
  - `PATCH /api/settings` oleh admin → 403; oleh superadmin → 200 dan tersimpan.
- [ ] **Step 2:** `php artisan test --filter=Register` → FAIL.
- [ ] **Step 3:** Implement (validasi form dibagi dengan Task 2 lewat satu `FormRequest` `AccountFormRequest`: name max 100, username rule, password min 8 + confirmed).
- [ ] **Step 4:** `php artisan test` + `npm test` → PASS.
- [ ] **Step 5:** Commit `feat: staff can sign themselves up on the offline edition`.

### Task 4: Form pembuat lisensi (app repo, sisi Ashira)

**Files:**
- Create: `tools/buat-lisensi/index.php`, `tools/buat-lisensi/LicenseForm.php`, `tools/Buat Lisensi.bat`, `.gitattributes`, `tests/Feature/LicenseFormTest.php`

**Interfaces:**
- Consumes: `App\Services\License\Keys`, `LicenseFile::issue/parse/valid`.
- Produces: `LicenseForm::handle(array $post, Keys $keys, string $historyFile): array{filename: string, content: string}` (melempar `InvalidArgumentException` dengan pesan Indonesia).

- [ ] **Step 1: Tes gagal** (`LicenseFormTest`, kunci di folder sementara):
  - ID 64 heksa + untuk `PT Mitra — komputer master` → `filename` `lisensi PT Mitra - komputer master.lic`, isi lolos `LicenseFile::valid(..., ID, now()->addYears(50))` dan tanpa `expires_at`; satu baris CSV ditambahkan ke riwayat.
  - ID bukan 64 heksa → `ID komputer harus 64 karakter 0-9/a-f`; untuk kosong → `Isi keterangan lisensi`.
  - tanpa `secret.key` → `Kunci rahasia tidak ada di laptop ini`.
- [ ] **Step 2:** `php artisan test --filter=LicenseForm` → FAIL.
- [ ] **Step 3:** Implement. `index.php` mem-boot app (`require vendor/autoload.php` + `bootstrap/app.php`, kernel console), GET = form, POST = `LicenseForm::handle` → header unduhan. Tolak bila `REMOTE_ADDR` bukan `127.0.0.1`. `.bat`: `php -S 127.0.0.1:8777 -t tools\buat-lisensi` + `start http://127.0.0.1:8777`. `.gitattributes`: `tools/ export-ignore`, `tests/ export-ignore`, `docs/ export-ignore`.
- [ ] **Step 4:** `php artisan test` → PASS; `git archive HEAD | tar -t` tidak memuat `tools/`.
- [ ] **Step 5:** Commit `feat: license form for the Ashira laptop`.

### Task 5: Uji beban dua titik scan

**Files:**
- Create (sparepartsoftware): `tests/load/Uji-Beban.ps1`

**Interfaces:**
- `Uji-Beban.ps1 -BaseUrl <url> -Username -Password [-Qty 20] [-PerClient 20]`: login (curl.exe + cookie jar + token CSRF dari halaman), `POST /api/invoices` multipart (PNG 1×1 buatan, nomor acak, 1 item kode `UJI-BEBAN` qty `-Qty`), dua job paralel masing-masing `-PerClient` × `POST /api/invoices/{id}/scans` `{code: UJI-BEBAN, stage: pick}`, lalu `DELETE /api/invoices/{id}`. Exit 0 hanya bila `picked_total == Qty`, tidak ada status ≥ 500, dan tidak ada teks `database is locked`.

- [ ] **Step 1:** Tulis skrip; jalankan ke `php artisan serve` dengan SQLite file (`DB_DATABASE=<tmp>.sqlite`, edisi offline, `LICENSE_ENFORCE=false`, superadmin uji) → laporan "berhasil 20, ditolak 20, total 20/20".
- [ ] **Step 2:** Bila total > Qty atau ada 5xx: perbaiki di `app/Services/ScanService.php` (UPDATE atomik `WHERE picked_qty < qty`) dengan tes PHPUnit yang gagal dulu, lalu ulangi Step 1.
- [ ] **Step 3:** Commit `test: two-scanner load script` (+ fix bila ada).

### Task 6: Logika kabel & pemeriksaan (installer, murni)

**Files:**
- Create: `scripts/Cable.psm1`, `tests/Cable.Tests.ps1`

**Interfaces:**
- `Get-CableAddress -Role master|scan [-Point 1|2]` → `'192.168.77.1' | '.2' | '.3'`.
- `Select-CableAdapter -Adapters <obj[]>` → adaptor atau `$null`; masukan objek `{InterfaceIndex, Name, InterfaceDescription, Virtual, MediaType, PhysicalMediaType, Status}`; hanya fisik & `802.3`, bukan nama/deskripsi `Wi-Fi|Wireless|Bluetooth|VPN|Hyper-V|VMware|VirtualBox|TAP`; lebih dari satu → yang `Status Up` dulu.
- `Get-PreflightResult -Role -Facts <hashtable>` → `@(@{ name; status = 'ok'|'warn'|'block'; message })`. `Facts`: `Is64Bit`, `WindowsBuild`, `SacState` (0/1/2), `FreeBytes`, `Adapter` (atau `$null`), `AdapterHasGateway`, `AdapterDhcpAddress` (string/null), `OtherSubnetUse` (bool), `Port80Busy` (bool).
- `ConvertTo-CableState`/`Read-CableState -Path` → simpan/baca `{interfaceIndex, dhcpWasEnabled, previousAddresses}` JSON.

- [ ] **Step 1: Tes Pester gagal**: alamat per peran/titik; adaptor (Wi-Fi + Ethernet → Ethernet; Hyper-V + USB Ethernet → USB; dua Ethernet satu Up → yang Up; tidak ada → `$null`); pemeriksaan: master 32-bit → block; SAC 1 atau 2 → block dengan pesan berisi `Smart App Control`; master disk 500 MB → block, scan 200 MB → ok; adaptor null → block `Port LAN tidak ditemukan`; adaptor punya gateway → block berisi `USB-LAN` (master dan scan); DHCP `192.168.1.20` tanpa gateway → block; adaptor sudah `192.168.77.x` → ok (pasang ulang); `OtherSubnetUse` → block; port 80 sibuk → warn untuk master, tidak ada untuk scan; state JSON pulang-pergi identik.
- [ ] **Step 2:** `Invoke-Pester .\tests\Cable.Tests.ps1` → FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Semua Pester (`Common, Build, Master, Scan, Cable`) PASS.
- [ ] **Step 5:** Commit `feat: cable address plan and pre-install checks`.

### Task 7: Terapkan kabel di pasang/copot (installer)

**Files:**
- Create: `scripts/preflight.ps1`, `scripts/Cable-Apply.ps1`
- Modify: `scripts/setup-master.ps1`, `scripts/setup-scan.ps1`, `scripts/uninstall.ps1`, `scripts/Scan.psm1` (`Find-MasterUrl`)

**Interfaces:**
- Consumes: Task 6.
- `preflight.ps1 -Role master|scan -ResultFile <json>` → mengumpulkan `Facts` dari sistem (`Get-NetAdapter -Physical`, `Get-NetIPConfiguration`, registry SAC, `Get-PSDrive C`, `Get-NetTCPConnection 80`), menulis hasil `Get-PreflightResult` + adaptor terpilih; exit 0 bila tak ada block, 2 bila ada.
- `Set-CableAddress -InterfaceIndex -Address -StateFile` (Cable-Apply.ps1): simpan state, `Set-NetIPInterface -Dhcp Disabled`, hapus alamat IPv4 manual, `New-NetIPAddress -PrefixLength 24` (tanpa gateway), tunggu ≤ 10 dtk lalu cek `AddressState`; `Duplicate` → `Restore-CableAddress` + throw `Alamat <a> sudah dipakai komputer lain`.
- `Restore-CableAddress -StateFile`: hapus alamat kabel, kembalikan DHCP bila `dhcpWasEnabled`, `Set-DnsClientServerAddress -ResetServerAddresses`.
- `setup-master.ps1 … [-Cable] [-Port <int>] [-KeepAwake]`; `setup-scan.ps1 -Cable -Point 1|2 -PairingCode …` (Server default `http://192.168.77.1`); `Find-MasterUrl -Address '192.168.77.1' -PairingCode -Get` (bukan `-Host`: variabel bawaan PowerShell) → URL dengan port 80 atau 8080 pertama yang lolos `Test-MasterConnection` (coba ulang 20 dtk).
- Scan sebelum `Set-CableAddress`: `Test-Connection <alamat titik> -Count 1 -Quiet` → true → exit 2 `Titik <n> sudah dipakai, pilih titik lain` tanpa mengubah adaptor (Review Focus #4).
- Master `-Cable`: firewall kabel (Global Constraints) + ikon "Info pemasangan" (`notepad INFO-PEMASANGAN.txt`); INFO memuat `Alamat kabel: http://192.168.77.1[:port]`. `-KeepAwake`: `powercfg /change standby-timeout-ac 0`, `hibernate-timeout-ac 0`, lid AC = 0; catat ke `data\power.json` (nilai lama) untuk dikembalikan saat copot.
- `uninstall.ps1`: `Restore-CableAddress` bila `cable.json` ada (master `data\`, scan root), hapus firewall kabel, kembalikan daya dari `power.json`.

- [ ] **Step 1:** Tes Pester gagal untuk `Find-MasterUrl` (80 gagal, 8080 lolos → URL `:8080`; keduanya gagal → `$null`) di `tests\Scan.Tests.ps1`.
- [ ] **Step 2:** FAIL → implement semua di atas → PASS; semua skrip lolos parse.
- [ ] **Step 3:** Uji nyata adaptor di laptop (admin, satu UAC): `Set-CableAddress` ke `192.168.77.1` pada Ethernet laptop (kabel tak dicolok) → `Get-NetIPAddress` menunjukkan alamat; `Restore-CableAddress` → DHCP kembali, alamat hilang (Review Focus #5).
- [ ] **Step 4:** Commit `feat: apply and restore the cable network in setup and uninstall`.

### Task 8: Halaman installer + build (installer)

**Files:**
- Modify: `installer/VerifikasiFaktur.iss`, `build.ps1`, `README.md`

**Interfaces:**
- Consumes: Task 7 skrip & argumen.
- Parameter senyap baru: `/CABLE=1`, `/POINT=1|2`, `/PORT=<n>`, `/KEEPAWAKE=0|1` (default 1). Dihapus: `/ADMINEMAIL`, `/ADMINNAME`, `/SKIPADMIN` (halaman & konsol superadmin hilang).

- [ ] **Step 1:** `.iss`: `ArchitecturesAllowed=x86compatible x64compatible`; halaman Peran → halaman **Pemeriksaan komputer** (memo hasil `preflight.ps1`, ikon ✅/⚠️/⛔; ada ⛔ → Lanjut nonaktif + tombol "Periksa lagi"; dropdown adaptor bila >1) → master: centang "Kabel LAN ke komputer scan" (default nyala) + "Jangan tidurkan komputer ini" (default nyala) → scan: centang kabel (default nyala), pilih Titik 1/2, kode pemasangan; alamat terisi `192.168.77.1`. Master di non-64-bit → ⛔ dari pemeriksaan. Hapus halaman superadmin, `CreateSuperadmin`, `/SKIPADMIN`.
- [ ] **Step 2:** `build.ps1`: setelah ISCC, `MpCmdRun.exe -Scan -ScanType 3 -File <setup>`; ancaman → throw; hasil scan ke RELEASE-NOTES.
- [ ] **Step 3:** `.\build.ps1 -AppRef HEAD` → Setup.exe ada, Defender bersih, RELEASE-NOTES memuat `Defender: bersih`.
- [ ] **Step 4:** README: alur lokasi baru (kabel, titik, akun pertama, daftar, form lisensi).
- [ ] **Step 5:** Commit `feat: pre-install checks, cable and scan-point pages in the installer`.

### Task 9: Uji pasang di laptop + review

**Files:** Modify: `README.md` (catatan hasil uji)

- [ ] **Step 1:** GUI tanpa UAC berarti: jalankan Setup → pilih Scan → halaman pemeriksaan tampil (Ethernet laptop terdeteksi) → Batal. Pilih Master → pemeriksaan → Batal.
- [ ] **Step 2:** Pasang ulang master senyap (satu UAC): `/VERYSILENT /ROLE=master /CABLE=1 /PORT=8080` → `/up` di `:8080` 200; Ethernet `192.168.77.1`; aturan firewall kabel ada; layanan jalan.
- [ ] **Step 3:** Lisensi lewat `Buat Lisensi.bat` → unggah → `/akun-pertama` → buat akun → `/daftar` petugas → login petugas.
- [ ] **Step 4:** Jalankan `Uji-Beban.ps1` ke `http://localhost:8080` → exit 0.
- [ ] **Step 5:** Copot (satu UAC) → Ethernet kembali DHCP, firewall/tugas/ikon hilang, data tetap; pasang lagi → lisensi & kode tetap.
- [ ] **Step 6:** Review akhir seluruh branch (kedua repo) oleh reviewer segar; perbaiki Critical/Important dengan TDD; commit.

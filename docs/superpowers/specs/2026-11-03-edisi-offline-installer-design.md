# Edisi offline (LAN/WiFi) + installer Windows — desain

Tanggal: 2026-11-03
Status: disetujui lewat diskusi, menunggu review spec

## Tujuan

MGI memilih penawaran **offline**: app berjalan di **komputer master** milik MGI, komputer scan
membukanya lewat WiFi kantor, tanpa hosting dan tanpa internet. Sebelum dipasang di MGI, software
dimatangkan dulu dan diuji lokal: **laptop pengembang = komputer master**, **komputer teman =
komputer scan**, terhubung satu WiFi.

**Berhasil bila:**
- Satu file `Verifikasi-Faktur-Setup-<versi>.exe` memasang komputer master atau komputer scan
  (dipilih di installer) tanpa internet, Laragon, Composer, atau npm.
- Dua titik scan bekerja bersamaan tanpa galat dan tanpa hitungan ganda.
- Data aman: backup per jam & harian otomatis, salinan di komputer scan dan flashdisk, dan bisa
  dipulihkan ke versi mana pun oleh superadmin.
- Komputer master restart/listrik padam → app hidup lagi sendiri.

## Keputusan

| Hal | Keputusan |
|---|---|
| Kode app | Satu kode di `sparepartscan` untuk edisi online & offline; beda lewat `.env` |
| Paket installer | Repo baru `Harrakyy/sparepartsoftware`: skrip build, installer, panduan, rilis `Setup.exe` |
| Database edisi offline | SQLite (mode WAL) |
| Installer | Satu `Setup.exe`, halaman pilihan peran: komputer master / komputer scan |
| Akses komputer scan | Ikon desktop membuka Microsoft Edge mode aplikasi ke alamat master |
| Alamat master | IP lokal (dikunci lewat router atau Windows); tanpa domain |
| Backup otomatis (master) | Per jam, simpan 48 versi; harian 23.00, simpan 30 versi; tidak membuat versi baru bila data tidak berubah |
| Salinan di komputer scan | Tiap jam ambil backup terbaru dari master; simpan 24 per jam + 7 harian |
| Flashdisk | Dicolok di komputer master; tombol di app memilih disk; flashdisk menyimpan 7 hari terakhir |
| Isi backup | Database saja (file PDF/foto faktur tidak, karena dihapus otomatis setelah 60 hari dan aslinya di Zahir) |
| Pemulihan | Superadmin pilih versi → Pulihkan; data saat itu dibackup dulu |
| Proteksi app | Penguncian ke komputer buatan sendiri (gratis): lisensi bertanda tangan Ashira, dicek offline tiap app jalan; kunci rahasia hanya di Ashira. Encoder berbayar di luar cakupan sekarang |

## Fase dan hasil

### Fase 1 — Pematangan app (`sparepartscan`)
1. Gabung `feat/kunci-login` ke main.
2. Rapikan temuan ponytail-audit (#2–#7: `el()` bersama, `showToast` bersama, helper baris bisa
   diklik, hapus `parseWholeNumber`/`formatRupiah`, tes contoh, `laravel/sail`); skrip
   `composer dev` tanpa `npm run dev`.
3. Font Inter disimpan di `public/vendor` (tanpa Google Fonts).
4. Pengaturan edisi: `APP_EDITION=online|offline` (default `online`). Edisi offline mengaktifkan
   menu Backup, jadwal backup, dan endpoint salinan untuk komputer scan.
5. SQLite siap produksi: `journal_mode=WAL`, `busy_timeout=5000`, `synchronous=NORMAL`, foreign
   key aktif. Uji beban: dua klien scan bersamaan ke faktur yang sama/berbeda → tidak ada galat
   "database locked" dan tidak ada hitungan melebihi jumlah faktur.
6. Jadwal Laravel (`schedule:run`) dijalankan oleh Windows Task Scheduler tiap menit (bukan
   `artisan serve`): hapus file 60 hari, backup per jam & harian.
7. Keamanan dependensi:
   - PHP minimal **8.3.36** (perbaikan php2shell CVE-2026-109618/109619); pengembangan dan
     paket memakai versi PHP yang sama.
   - `composer audit` wajib bersih sebelum setiap rilis (saat ini: `symfony/mime`
     CVE-2026-45070 diperbaiki).
   - App tidak dibuka lewat tunnel/internet selama PHP belum diperbarui.

### Fase 2 — Backup & pemulihan (fitur app, edisi offline)
- `php artisan app:backup {hourly|daily|manual}`:
  - `VACUUM INTO` ke file sementara → `PRAGMA integrity_check` → pindah ke
    `backup/perjam/` atau `backup/harian/` dengan nama `faktur-YYYYMMDD-HHmm.sqlite`.
  - Bila isinya identik (hash) dengan backup terakhir di folder itu, file baru dibuang.
  - Rotasi: perjam 48, harian 30 (yang terlama dihapus).
- Menu **Backup** (superadmin, edisi offline):
  - Daftar versi: waktu, jenis, ukuran, jumlah faktur.
  - **Pulihkan**: ketik `PULIHKAN` untuk konfirmasi → backup `sebelum-pulihkan` dibuat → app masuk
    mode perawatan → file database diganti → migrasi dijalankan → app aktif lagi.
  - **Backup ke flashdisk**: daftar disk lepas-pasang di komputer master (via PowerShell
    `Get-Volume`); pilih disk → salin backup terbaru + 7 backup harian terakhir ke
    `<disk>:\VerifikasiFaktur-Backup\`, hapus yang lebih lama dari 7 hari di flashdisk.
    Tampilkan "terakhir backup ke flashdisk"; peringatan bila > 7 hari.
- Salinan untuk komputer scan: `GET /backup/latest` (di luar login, wajib header
  `X-Pairing-Code` = kode pemasangan dari installer; salah kode → 403, dibatasi 10/menit).
- **Darurat superadmin**: `php artisan app:superadmin --reset` (lewat shortcut "Reset superadmin"
  di komputer master, perlu hak admin Windows).

### Fase 3 — Installer (`sparepartsoftware`)
- **Build**: `build.ps1` mengunduh PHP 8.3 (x64, TS) dan Apache 2.4 (Apache Lounge) versi tetap
  dengan cek checksum, mengambil `sparepartscan` pada tag versi, `composer install --no-dev`,
  lalu Inno Setup membuat `Verifikasi-Faktur-Setup-<versi>.exe`.
- **Halaman peran**: Komputer master / Komputer scan.
- **Peran master**:
  - Pasang ke `C:\VerifikasiFaktur\` (`app\`, `php\`, `apache\`, `data\database.sqlite`,
    `backup\`); `.env` edisi offline dibuat dengan `APP_KEY` dan kode pemasangan acak.
  - Apache didaftarkan sebagai layanan Windows (otomatis saat komputer nyala); port 80, bila
    terpakai pakai 8080.
  - Aturan firewall untuk port itu (profil Private); peringatan bila jaringan berstatus Public.
  - Task Scheduler: `schedule:run` tiap menit (akun SYSTEM).
  - Langkah "Buat akun superadmin" (nama, email, password) pada pemasangan pertama.
  - **Aktivasi lisensi** (lihat bab "Penguncian komputer"): tampilkan ID komputer; isi file
    `license.lic` dari Ashira; app menolak jalan tanpa lisensi yang sah.
  - Ikon desktop "Verifikasi Faktur" (Edge mode aplikasi) + "Reset superadmin".
  - Halaman akhir: alamat server (IP) + kode pemasangan; peringatan bila IP belum dikunci.
  - **Update**: Setup versi baru mendeteksi pemasangan lama → backup `sebelum-update` → ganti
    `app\` (data dan `.env` tetap) → migrasi.
  - **Pulihkan saat pasang baru**: opsi memilih file backup (dari flashdisk/komputer scan).
- **Peran scan**:
  - Isi alamat server + kode pemasangan → cek koneksi (`/up`) dan kode; pesan jelas bila gagal.
  - Ikon desktop "Verifikasi Faktur" (Edge `--app=<alamat>`).
  - Task Scheduler tiap jam: skrip PowerShell mengambil `/backup/latest` ke
    `C:\VerifikasiFaktur\backup\` (rotasi 24 per jam + 7 harian).
  - Tidak memasang PHP/Apache/database.
- Ganti peran: jalankan Setup lagi dan pilih peran lain.
- **Keamanan PHP di paket**: `build.ps1` menolak build bila PHP di bawah versi minimum
  (8.3.36 saat ini) atau `composer audit` tidak bersih. Versi PHP, Apache, dan app dicatat di
  catatan rilis dan ditampilkan di halaman "Tentang" app. Bila ada celah kritis PHP, kirim Setup
  versi baru (alur update tanpa kehilangan data); hal ini disepakati dengan MGI karena edisi
  offline tanpa maintenance.

## Penguncian komputer (lisensi offline bertanda tangan)

Tujuan: edisi offline hanya jalan di komputer master yang diizinkan Ashira, dan **tidak boleh
"telepon ke rumah"** — app tetap offline, hanya file lisensi yang dicek. Mencegah penyalinan
folder ke komputer lain; bukan proteksi terhadap pembongkaran kode (itu perlu encoder, di luar
cakupan). Hanya aktif di `APP_EDITION=offline`.

**Kunci:** sepasang Ed25519. **Kunci publik** disertakan di kode app (`config/license.php` atau
file di repo); **kunci rahasia hanya di laptop Ashira**, masuk `.gitignore`, tidak pernah di
installer/app/repo. Dibuat sekali dengan `php artisan license:keygen` (menolak menimpa kunci yang ada).

**ID komputer:** gabungan beberapa nilai hardware Windows yang stabil (UUID motherboard via
`Win32_ComputerSystemProduct`, serial disk sistem), di-hash → string. `php artisan license:id`
menampilkannya. Satu-dua nilai boleh berubah tanpa menggagalkan lisensi (lihat toleransi).

**File lisensi** `storage/app/license.lic` (JSON + tanda tangan): `machine_id`, `issued_to`,
`issued_at`, `expires_at` (opsional/null = selamanya), `edition`, dan `signature` (tanda tangan
Ed25519 atas isinya).

**Alat pembuat lisensi (sisi Ashira)** `php artisan license:buat {machine_id} --untuk= --sampai=`:
baca kunci rahasia, susun payload, tandatangani, tulis `license.lic`. Jalan di laptop mana pun yang
punya kunci rahasia; tanpa kunci → gagal.

**Pengecekan saat jalan** (middleware `web`, hanya edisi offline; di-cache beberapa menit agar ringan):
tanda tangan sah dengan kunci publik, `machine_id` cocok dengan ID komputer sekarang, dan belum
kedaluwarsa. Gagal → halaman "Aktivasi diperlukan" berisi ID komputer + cara menghubungi Ashira
(bukan 500, bukan login). Rute `/aktivasi` (unggah/tempel `license.lic`) tetap terbuka saat belum
aktif; rute lain diblokir.

**Aktivasi di installer master:** tampilkan ID komputer untuk disalin; terima `license.lic` (file
atau tempel teks) lalu verifikasi. Edisi online tidak memeriksa lisensi.

**Pemulihan/pindah komputer:** ganti hardware → ID berubah → minta lisensi baru ke Ashira
(disebut di panduan; disepakati dengan MGI). Toleransi: ID dari ≥ N dari M nilai hardware cocok,
supaya ganti satu disk data tidak langsung mematikan.

### Fase 4 — Uji coba lokal
Laptop pengembang (master) + komputer teman (scan), satu WiFi, dipasang dengan installer:
- Upload laporan Penjualan Rincian asli; scan dari dua titik bersamaan; cetak di masing-masing printer.
- Putus WiFi di tengah scan → pesan jelas, scan ulang tidak dobel.
- Restart komputer master / cabut listrik → app hidup lagi sendiri; komputer scan tersambung lagi.
- Backup per jam & harian terbentuk; salinan muncul di komputer scan; backup ke flashdisk;
  pulihkan versi lama; update ke versi baru tanpa kehilangan data.
- Dipakai beberapa hari dengan PDF Zahir asli; semua masalah dicatat dan diperbaiki.

### Fase 5 — Panduan
Di `sparepartsoftware/docs`: panduan pemasangan (termasuk mengunci IP, driver HPRT, pengaturan
cetak Edge), panduan 1 halaman per peran (admin upload, petugas scan, superadmin), checklist hari H
(dari daftar kendala lokasi).

## Pengujian

- PHP: edisi offline vs online (menu & rute Backup hanya di offline), `app:backup` (versi, hash
  sama dibuang, rotasi, integritas), pulihkan (backup pengaman, data kembali), salin ke disk
  (rotasi 7 hari, disk tidak ada), `/backup/latest` (kode salah 403, batas percobaan), reset
  superadmin.
- Uji beban SQLite dua klien bersamaan (skrip).
- Lisensi: tanda tangan sah diterima; tanda tangan diubah/palsu ditolak; `machine_id` beda ditolak
  (simulasi folder disalin); kedaluwarsa ditolak; tanpa `license.lic` → halaman aktivasi, rute lain
  diblokir; edisi online tidak memeriksa lisensi; `license:buat` tanpa kunci rahasia gagal.
- Installer: dipasang di Windows 11 (master) dan Windows 10/11 (scan) dengan checklist Fase 4.

## Di luar cakupan

Pemasangan di lokasi MGI (setelah uji lokal lulus), versi online di Hostinger, update otomatis
lewat internet, backup file PDF/foto, aplikasi HP khusus, encoder/obfuscator PHP (kode tetap
terbaca; dipertimbangkan bila produk dijual ke banyak klien), server lisensi online.

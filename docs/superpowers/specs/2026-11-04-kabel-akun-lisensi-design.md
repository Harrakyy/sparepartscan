# Mode kabel, akun tanpa repot, dan alat lisensi — desain

Tanggal: 2026-11-04
Status: disetujui lewat diskusi, menunggu review spec
Melanjutkan: `2026-11-03-edisi-offline-installer-design.md` (bagian yang diubah disebut di sini)

## Tujuan

Pemasangan di MGI (1 komputer master Windows 11 + 2 komputer scan Windows 10, lantai bawah & atas)
dilakukan sendiri oleh Ashira dalam ±15 menit tanpa mengetik IP, tanpa jendela konsol, dan tanpa
kejutan di lokasi. Jaringan: **kabel LAN** lewat switch (WiFi di luar cakupan).

**Berhasil bila:**
- Satu `Setup.exe` untuk semua komputer (master wajib 64-bit; scan 32- atau 64-bit).
- Masalah lingkungan (Smart App Control, port LAN terpakai, bentrok alamat, disk) terlihat di halaman
  pemeriksaan **sebelum** apa pun dipasang.
- Komputer scan tersambung ke alamat tetap master; alamat tidak pernah berubah.
- Akun pertama dibuat lewat halaman web; petugas mendaftar sendiri di komputer scan.
- Lisensi dibuat lewat form di laptop Ashira, satu klik → file `.lic` terunduh.
- Dua titik scan bersamaan tidak menghasilkan hitungan dobel/melebihi qty (diuji skrip).

## Keputusan

| Hal | Keputusan |
|---|---|
| Login | **Username saja** (kedua edisi). `email` diganti `username`; tidak ada email di form akun |
| Format username | `^[a-z0-9._-]{3,30}$`, disimpan huruf kecil, unik |
| Akun pertama | Halaman web **"Buat akun superadmin"**, hanya edisi offline, hanya selama tabel `users` kosong |
| Pendaftaran mandiri | Tombol **Daftar** di halaman login, hanya edisi offline; akun baru = petugas gudang (`admin`), langsung masuk; sakelar superadmin "Izinkan pendaftaran" (default **nyala**); batas 5 percobaan/menit per IP |
| Lisensi | Form lokal di laptop Ashira (`Buat Lisensi.bat`); **selamanya** (tanpa tanggal); riwayat dicatat |
| Jaringan | Kabel LAN, subnet tetap `192.168.77.0/24`: master `.1`, scan titik 1 `.2`, titik 2 `.3`; tanpa gateway/DNS |
| Firewall | Aturan tambahan "Verifikasi Faktur (kabel)": TCP port app, `RemoteAddress 192.168.77.0/24`, **semua profil** (kabel langsung selalu "Unidentified network" = Public, dan kembali Public tiap restart) |
| Arsitektur | Satu Setup.exe (`x86compatible x64compatible`); peran master hanya di 64-bit |
| Kode pemasangan | Tetap; dibaca lewat ikon desktop **"Info pemasangan"** di master |
| Superadmin di installer | Halaman nama/email + jendela konsol **dihapus** (diganti halaman web akun pertama) |

## A. App (`sparepart`)

1. **Username.** Migrasi: tambah kolom `username` unik, isi dari bagian sebelum `@` email (huruf kecil,
   karakter di luar `[a-z0-9._-]` dibuang; < 3 karakter → `user<id>`; bentrok → akhiran `-2`, `-3`, …),
   lalu hapus `email` dan `email_verified_at`. Login, menu Pengguna (form + tabel), `UserController`,
   `UserFactory`, `app:superadmin {username?}` (+ `--reset`), dan semua tes memakai username. Pesan
   login: "Username atau password salah". Penguncian login per akun tetap.
2. **Akun pertama** `GET/POST /akun-pertama` (edisi offline, di luar `auth`, setelah lisensi): hanya bila
   `User::count() === 0`, selain itu 404. Field: nama, username, password + ulangi (min. 8). Membuat
   superadmin aktif lalu langsung login → `/gudang`. Halaman login edisi offline dialihkan ke sini
   selama belum ada akun; aktivasi lisensi sukses → ke sini bila belum ada akun, selain itu `/login`.
3. **Daftar** `GET/POST /daftar` (edisi offline, tamu): hanya bila setelan `allow_registration` nyala,
   selain itu 404 dan tombol Daftar disembunyikan. Field sama dengan akun pertama; role `admin`, aktif,
   langsung login. `throttle:5,1`.
4. **Setelan** tabel `settings (key PK, value)` + `Setting::get/set`. `allow_registration` default `1`.
   Sakelar di menu Pengguna (superadmin, edisi offline) lewat `PATCH /api/settings`.
5. **Pesan admin.** Perintah artisan yang menyentuh database/`storage` gagal karena izin → pesan
   "Jalankan sebagai administrator (klik kanan → Run as administrator)" (bukan "database tidak ada").

## B. Alat pembuat lisensi (repo `sparepart`, sisi Ashira)

- `tools/buat-lisensi/index.php` + `tools/Buat Lisensi.bat` (menjalankan `php -S 127.0.0.1:8777`
  dan membuka browser). Hanya mendengar `127.0.0.1`; menolak jalan bila `secret.key` tidak ada.
- Form: **ID komputer** (64 heksa, divalidasi), **Untuk** (mis. "PT Mitra Ghanim — komputer master").
  Tombol Buat → unduh `lisensi <untuk>.lic` (karakter tak sah di nama file diganti `-`), tanpa tanggal
  kedaluwarsa. Memakai `LicenseFile`/`Keys` yang sama dengan app.
- Riwayat: baris CSV (waktu, untuk, ID) di `storage/app/keys/riwayat-lisensi.csv` (ikut terabaikan git).
- `tools/` diberi `export-ignore` di `.gitattributes` → tidak ikut `git archive`/installer.

## C. Installer (`sparepartsoftware`)

1. **Halaman Pemeriksaan komputer** (setelah pilih peran). Status per baris: ✅ / ⚠️ / ⛔; ⛔ menonaktifkan Lanjut.
   | Pemeriksaan | Master | Scan |
   |---|---|---|
   | Windows 10/11; master 64-bit | ⛔ bila bukan 64-bit | info 32/64-bit |
   | Smart App Control (`VerifiedAndReputablePolicyState`) | ⛔ bila On/Evaluation + cara | sama |
   | Ruang disk | ⛔ < 1 GB | ⛔ < 100 MB |
   | Port LAN fisik ada | ⛔ bila tidak ada | ⛔ bila tidak ada |
   | Port LAN dipakai jaringan kantor (punya gateway / alamat DHCP non-169.254, bukan `192.168.77.x`) | ⛔ + saran adaptor USB-LAN | ⛔ sama (alamatnya akan diganti, internet kantor di PC itu putus) |
   | Subnet `192.168.77.x` sudah dipakai adaptor lain | ⛔ | ⛔ |
   | Port 80 terpakai | ⚠️ pakai 8080 | — |
2. **Pilihan adaptor**: hanya adaptor fisik non-nirkabel (abaikan Wi-Fi, Bluetooth, VPN, Hyper-V/VMware);
   boleh kabel belum tersambung (pilih berdasar `InterfaceIndex`, bukan status Up). Lebih dari satu →
   pilihan di halaman pemeriksaan (default: yang kabelnya tersambung).
3. **Pasang alamat** (`Set-CableAddress`): simpan konfigurasi lama ke `cable.json`, matikan DHCP IPv4,
   hapus alamat manual lama, `New-NetIPAddress` `/24` tanpa gateway. Scan: sebelum memasang, alamat
   tujuan diping; menjawab → ⛔ "Titik N sudah dipakai, pilih titik lain". Sesudah memasang:
   `AddressState Duplicate` → kembalikan & ⛔.
4. **Master**: peran master + mode kabel (dicentang default) → alamat `.1`, aturan firewall kabel,
   **jangan sleep saat dicolok listrik** + tutup layar = diam (dicentang default, `powercfg`), ikon
   "Info pemasangan". `/PORT=` untuk memaksa port (laptop Ashira: 8080, bentrok Laragon).
5. **Scan**: pilih **Titik 1/2**; alamat master terisi `192.168.77.1`; coba port 80 lalu 8080; ketik kode
   pemasangan → cek koneksi (dengan coba ulang 20 detik setelah alamat dipasang) → pasang ikon & tugas
   tarik backup (alamat tersimpan tetap).
6. **Copot**: kembalikan adaptor ke DHCP sesuai `cable.json`, hapus aturan firewall kabel, kembalikan
   setelan daya hanya bila installer yang mengubahnya.
7. **Build**: scan Defender (`MpCmdRun -Scan -ScanType 3`) pada Setup.exe; ancaman → build gagal.

## Risiko → penanganan

| Risiko | Penanganan |
|---|---|
| Smart App Control memblokir php/httpd/Setup (tak bertanda tangan) | Pemeriksaan ⛔ sebelum pasang; cek di MGI sebelum hari H. Jalan keluar (di luar cakupan): MGI mematikan SAC atau sertifikat tanda tangan kode |
| Antivirus salah deteksi installer Inno | Scan Defender tiap build; bila ditandai, kirim ke Microsoft |
| SmartScreen "Windows protected your PC" | File via flashdisk tanpa Mark-of-the-Web; bila muncul: More info → Run anyway |
| Dua scan memilih titik sama | Ping alamat tujuan sebelum pasang + cek Duplicate |
| Jaringan kantor memakai `192.168.77.x` | Pemeriksaan subnet ⛔ |
| Port LAN master dipakai internet kantor | Pemeriksaan ⛔ + saran adaptor USB-LAN |
| Adaptor virtual / kabel belum dicolok | Pilih adaptor fisik berdasar InterfaceIndex |
| Kabel langsung = Public, reset tiap restart | Aturan firewall `RemoteAddress` subnet kabel, semua profil |
| Master tidur | `powercfg` saat pasang |
| Port 80 terpakai | 8080 otomatis; scan mencoba 80 lalu 8080 |
| Hitungan dobel 2 titik | Skrip uji beban 2 klien bersamaan sebelum ke MGI |
| Pendaftaran disalahgunakan | Hanya offline + jaringan kabel tertutup + sakelar + throttle; superadmin bisa menonaktifkan akun |

## Pengujian

- PHPUnit: login username (sukses, salah, terkunci), migrasi email→username, akun pertama (muncul
  saat kosong, 404 setelah ada akun, tidak ada di edisi online), daftar (sakelar, throttle, role
  `admin`, tidak ada di edisi online), aktivasi → akun pertama, `app:superadmin` username.
- Pester: rencana alamat per peran/titik, keputusan pemeriksaan (SAC, port LAN kantor, subnet bentrok,
  disk, arsitektur) dari data adaptor palsu, simpan/kembalikan `cable.json`.
- Alat lisensi: form → file sah (diverifikasi `LicenseFile::valid`), ID salah ditolak, tanpa `secret.key` gagal.
- Laptop Ashira: GUI installer master (port 8080) & scan sampai halaman Siap (Batal); pasang master,
  aktivasi lewat form lisensi, akun pertama, daftar petugas.
- Skrip uji beban: dua klien paralel scan faktur yang sama → total ≤ qty, tanpa galat.
- Review akhir seluruh branch.

## Di luar cakupan

WiFi, versi online di Hostinger, sertifikat tanda tangan kode, lebih dari 2 titik scan (rancangan
`.2`–`.3` bisa diperluas nanti), push ke GitHub (menunggu perintah).

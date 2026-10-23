# Verifikasi Faktur Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aplikasi Laravel tempat admin mengunggah faktur Zahir (PDF/foto), lalu gudang men-scan barang per faktur dan sistem menandai cocok (hijau) atau salah (merah).

**Architecture:** Laravel 12 menyajikan halaman Blade dan API JSON di bawah `/api`. Logika domain ada di dua service (`InvoiceService`, `ScanService`) yang diuji lewat feature test. Pembacaan PDF/foto dan penguraian faktur berjalan di browser admin (`pdf.js`, `Tesseract.js`, `invoice-parser.js` yang murni dan diuji dengan `node --test`). Gudang memakai scanner USB mode keyboard yang ditangkap `keyboard-wedge.js`.

**Tech Stack:** PHP 8.2, Laravel 12, MySQL 8.4 (Laragon), PHPUnit (SQLite in-memory), Blade + JavaScript ES module tanpa build, `pdfjs-dist` 6.4.299, `tesseract.js` 7.0.0 + `tesseract.js-core` 7.0.0 + `@tesseract.js-data/eng` 1.0.0, Node 24 hanya untuk test JS.

**Spec:** `docs/superpowers/specs/2026-10-23-verifikasi-faktur-design.md`

## Global Constraints

- Tanpa login di tahap ini. Tanpa Vite / build step: CSS dan JS ditulis langsung di `public/`.
- Respons API selalu `{ "success": bool, "data": any|null, "error": string|null }`, termasuk error validasi (422) dan 404.
- Status faktur: `waiting`, `picking`, `picked`, `qc`, `qc_done`. Tahap scan: `pick`, `qc`. Hasil scan: `ok`, `over_qty`, `not_in_invoice`.
- Batas: nomor faktur 1–32 digit; penerima 1–150 karakter; kode part 1–64 karakter, disimpan huruf besar dan di-trim; nama barang 1–255; qty bilangan bulat 1–10.000; file `pdf,jpg,jpeg,png` maks 10 MB.
- File faktur disimpan di disk `local` folder `invoices/` dengan nama acak; dihapus otomatis setelah 60 hari.
- Waktu disimpan UTC, ditampilkan WIB (`Asia/Jakarta`).
- Teks UI bahasa Indonesia; nilai dari server dirender dengan `textContent`. Palet putih lembut `#F7F7F5`, hitam lembut `#232323`, hijau `#3F7A57`, merah `#A8321F`.
- Pesan persis: kelebihan → `{KODE} sudah lengkap ({n}/{n})`; tidak ada → `{KODE} tidak ada di faktur`; selesai ditolak → `Masih ada barang yang belum lengkap`; ganti faktur ditolak → `Faktur {NOMOR} sudah mulai di-scan, tidak bisa diganti`.
- Commit format conventional, tanpa baris attribution.

## Review Focus

1. **Scan dobel cepat** (scanner menembak dua kali dalam <100 ms pada barang dengan qty 1): yang kedua harus `over_qty`, `picked_qty` tetap 1. → test di Task 3.
2. **Kode dengan huruf kecil / spasi** (`" s-tks-002 "`): dicocokkan sebagai `S-TKS-002`. → test di Task 3.
3. **Kode berisi garis miring** (`H-MTE-009/010`) lewat URL dan JSON tetap utuh. → test di Task 3.
4. **Unggah ulang nomor faktur yang sama** setelah ada scan: ditolak dan data lama utuh. → test di Task 2.
5. **Teks OCR dengan salah baca** (`S-SCS-OO2`, jumlah `2` terbaca `Z`): parser tetap menghasilkan baris dan memberi peringatan, tidak membuang baris. → test di Task 5.

---

## File Structure

| File | Tanggung jawab |
|---|---|
| `app/Models/{Invoice,InvoiceItem,ScanEvent}.php` | Model Eloquent + relasi |
| `app/Services/InvoiceService.php` | Buat/ganti faktur dari data konfirmasi, gabung kode dobel |
| `app/Services/ScanService.php` | Catat scan, selesaikan tahap |
| `app/Http/Controllers/Api/*.php` | Controller tipis: validasi → service → JSON |
| `app/Http/Requests/StoreInvoiceRequest.php` | Validasi unggah faktur |
| `app/Support/ApiResponse.php` | Helper envelope JSON |
| `app/Console/Commands/PurgeInvoiceFiles.php` | Hapus file > 60 hari |
| `routes/{web,api,console}.php` | Route halaman, API, jadwal |
| `resources/views/*.blade.php` | Layout, faktur baru, daftar sesi, sesi, laporan |
| `public/js/invoice-parser.js` | Urai teks faktur (murni) |
| `public/js/similar-code.js` | Levenshtein + saran kode (murni) |
| `public/js/keyboard-wedge.js`, `format.js` | Dipindah dari aplikasi lama |
| `public/js/pages/*.js` | Logika tiap halaman |
| `public/vendor/{pdfjs,tesseract}/` | Library offline |
| `tests/Feature/*`, `tests-js/*.test.js` | Test PHP dan JS |

---

### Task 1: Scaffold Laravel, hapus aplikasi Node lama

**Files:**
- Delete: `src/`, `public/` (lama), `test/`, `package.json`, `package-lock.json`, `README.md`
- Create: proyek Laravel 12 di root repo; `package.json` baru (hanya untuk test JS); `tests-js/`; `public/js/keyboard-wedge.js`, `public/js/format.js` (salin dari versi lama); `tests-js/keyboard-wedge.test.js`, `tests-js/format.test.js` (salin, ubah path import)

**Interfaces:**
- Produces: `npm test` → `node --test "tests-js/**/*.test.js"`; `php artisan test`; `.env` lokal `DB_CONNECTION=mysql`, `DB_DATABASE=sparepart`; `phpunit.xml` memakai SQLite `:memory:`.

- [ ] **Step 1:** Simpan salinan `public/js/keyboard-wedge.js`, `public/js/format.js`, `test/keyboard-wedge.test.js`, `test/format.test.js` ke folder sementara, lalu `git rm -r` file Node lama (daftar di atas; `docs/` dan `.gitignore` tetap).
- [ ] **Step 2:** `composer create-project laravel/laravel:^12.0 _app` lalu pindahkan isinya ke root repo (gabungkan `.gitignore`: tambahkan `.superpowers/`). Hapus `vite.config.js`, `resources/js`, `resources/css`, dan dependensi Vite di `package.json`; isi `package.json` hanya `{"private":true,"type":"module","scripts":{"test":"node --test \"tests-js/**/*.test.js\""}}`.
- [ ] **Step 3:** Kembalikan 4 file JS ke `public/js/` dan `tests-js/`, perbaiki path import ke `../public/js/...`.
- [ ] **Step 4:** Set `.env` untuk MySQL Laragon (`DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=sparepart`, `DB_USERNAME=root`, `DB_PASSWORD=`), `APP_TIMEZONE` tetap `UTC`, `APP_LOCALE=id`. Pastikan `phpunit.xml` berisi `DB_CONNECTION=sqlite` dan `DB_DATABASE=:memory:`.
- [ ] **Step 5: Verifikasi.** Run: `php artisan test` → contoh test bawaan PASS. Run: `npm test` → semua test wedge & format PASS.
- [ ] **Step 6: Commit** — `chore: replace Node app with Laravel 12 skeleton`

---

### Task 2: Skema, model, dan simpan faktur

**Files:**
- Create: 3 migration, 3 model + factory, `app/Services/InvoiceService.php`, `app/Support/ApiResponse.php`, `app/Http/Requests/StoreInvoiceRequest.php`, `app/Http/Controllers/Api/InvoiceController.php`, `routes/api.php` (aktifkan lewat `bootstrap/app.php` `withRouting(api: ...)`)
- Test: `tests/Feature/InvoiceApiTest.php`

**Interfaces:**
- Produces:
  - Tabel & kolom persis spec bagian 7.
  - `ApiResponse::ok(mixed $data, int $status = 200): JsonResponse`, `ApiResponse::fail(string $message, int $status): JsonResponse`. Exception handler di `bootstrap/app.php`: `ValidationException` → 422 envelope (pesan pertama), `ModelNotFoundException`/`NotFoundHttpException` di `api/*` → 404 envelope.
  - `InvoiceService::save(array $data, UploadedFile $file): Invoice` — `$data = ['number','customer_name','invoice_date'?, 'source_type','items'=>[['code','name','qty'],...]]`; gabung kode dobel (qty dijumlah, nama pertama); ganti faktur lama bila tanpa scan; lempar `InvoiceLockedException` bila sudah ada scan.
  - `POST /api/invoices` (multipart: `file` + `payload` JSON string) → 201 `Invoice` dengan `items`.
  - `GET /api/invoices?status=open|done&q=` (`open` = waiting/picking, `done` = picked/qc/qc_done) → 50 terbaru, dengan `items_count`, `total_qty`, `picked_total`.
  - `GET /api/invoices/{invoice}` → invoice + items (urut `line_no`) + 30 `scan_events` terbaru.

- [ ] **Step 1: Test gagal** (`InvoiceApiTest`), memakai `Storage::fake('local')` dan `UploadedFile::fake()->create('f.pdf', 100, 'application/pdf')`:
```php
// simpan faktur 00066365 dengan 9 item → 201, data.items count 9, status 'waiting', file tersimpan di invoices/
// kode ' s-tks-002 ' tersimpan 'S-TKS-002'
// dua baris kode sama (qty 1 dan 2) → satu item qty 3
// nomor sama, belum ada scan → 201, item lama diganti item baru, id invoice sama
// nomor sama, sudah ada scan_event → 409, error 'Faktur 00066365 sudah mulai di-scan, tidak bisa diganti', item lama utuh
// qty 0 → 422 envelope; file .exe → 422; tanpa items → 422
// GET /api/invoices?status=open berisi faktur; ?q=akbar menemukan; ?q=xyz kosong
// GET /api/invoices/999 → 404 envelope
```
- [ ] **Step 2:** `php artisan test --filter=InvoiceApiTest` → FAIL.
- [ ] **Step 3:** Implementasi. Validasi `StoreInvoiceRequest` sesuai Global Constraints (payload di-decode sebelum validasi lewat `prepareForValidation`). Simpan file lewat `$file->store('invoices', 'local')`. Seluruh simpan/ganti dalam `DB::transaction`.
- [ ] **Step 4:** Test PASS.
- [ ] **Step 5: Commit** — `feat: invoice schema and upload API`

---

### Task 3: Scan dan penyelesaian tahap

**Files:**
- Create: `app/Services/ScanService.php`, `app/Http/Controllers/Api/ScanController.php`
- Test: `tests/Feature/ScanApiTest.php`

**Interfaces:**
- Consumes: model & `ApiResponse` (Task 2).
- Produces:
  - `ScanService::scan(Invoice $invoice, string $code, string $stage, bool $manual): array` → `['result' => 'ok'|'over_qty'|'not_in_invoice', 'message' => ?string, 'item_id' => ?int]`. Normalisasi kode: `strtoupper(trim())`. Tambah qty memakai `UPDATE ... SET picked_qty = picked_qty + 1 WHERE id = ? AND picked_qty < qty` (atau `qc_qty`) di dalam transaksi bersama insert `scan_events`. Status: `waiting` → `picking` pada scan `pick` pertama; scan `qc` hanya boleh saat status `picked` atau `qc` (pertama kali → `qc`), selain itu 409 `Selesaikan scan pertama dulu`. Scan `pick` saat status sudah `picked`/`qc`/`qc_done` → 409 `Scan pertama sudah selesai`.
  - `ScanService::complete(Invoice $invoice, string $stage): Invoice` — semua item lengkap untuk tahap itu, selain itu 409 `Masih ada barang yang belum lengkap`; `pick` → `picked` + `picked_at`, `qc` → `qc_done` + `qc_done_at`.
  - `POST /api/invoices/{invoice}/scans` `{code, stage, manual}` → 200 `{ scan: {...}, invoice: <detail sama dengan GET> }` (hasil `over_qty`/`not_in_invoice` tetap 200, bukan error).
  - `POST /api/invoices/{invoice}/complete` `{stage}` → 200 detail invoice.

- [ ] **Step 1: Test gagal** (`ScanApiTest`):
```php
// scan kode ada → result ok, picked_qty 1, status 'picking', scan_events 1 baris
// item qty 1 di-scan dua kali berturut → kedua 'over_qty', message 'S-MCS-005 sudah lengkap (1/1)', picked_qty tetap 1
// kode ' s-tks-002 ' → ok pada item S-TKS-002
// kode 'H-MTE-009/010' → ok
// kode 'H-MTE-005' → 'not_in_invoice', message 'H-MTE-005 tidak ada di faktur', invoice_item_id null
// manual true tersimpan di scan_events
// complete pick saat belum lengkap → 409 'Masih ada barang yang belum lengkap'
// semua item lengkap → complete pick → status 'picked', picked_at terisi
// scan pick setelah picked → 409; scan qc sebelum picked → 409
// qc: scan semua → complete qc → 'qc_done'; qc_qty terpisah dari picked_qty
// stage 'abc' → 422
```
- [ ] **Step 2:** FAIL. **Step 3:** implementasi. **Step 4:** PASS.
- [ ] **Step 5: Commit** — `feat: scan matching and stage completion API`

---

### Task 4: Laporan dan hapus file 60 hari

**Files:**
- Create: `app/Http/Controllers/Api/ReportController.php`, `app/Console/Commands/PurgeInvoiceFiles.php`; Modify: `routes/console.php` (jadwal harian 02:00)
- Test: `tests/Feature/ReportApiTest.php`, `tests/Feature/PurgeInvoiceFilesTest.php`

**Interfaces:**
- Produces:
  - `GET /api/reports/outgoing?from=YYYY-MM-DD&to=YYYY-MM-DD&q=` (tanggal WIB, default hari ini) → `{ invoices: [{number, customer_name, picked_at, items:[{part_code, part_name, qty}]}], totals: [{part_code, part_name, qty}] }`, hanya status `picked|qc|qc_done`, filter `picked_at` dalam rentang WIB.
  - `php artisan invoices:purge-files` → hapus file faktur `created_at` < sekarang − 60 hari, set `file_path = null`, `file_purged_at = now()`, cetak jumlah.
  - `GET /files/invoices/{invoice}` (route web) → stream file; 404 bila sudah dihapus.

- [ ] **Step 1: Test gagal**:
```php
// faktur picked hari ini muncul; faktur 'picking' tidak muncul; faktur picked kemarin tidak muncul tanpa from
// totals menjumlah qty per part_code lintas faktur
// q=akbar menyaring penerima
// purge: faktur umur 61 hari → file hilang dari Storage, file_path null; umur 59 hari → tetap
```
- [ ] **Step 2:** FAIL. **Step 3:** implementasi (`Carbon::now('Asia/Jakarta')` untuk batas tanggal, dikonversi ke UTC untuk query). **Step 4:** PASS.
- [ ] **Step 5: Commit** — `feat: outgoing report and 60-day invoice file purge`

---

### Task 5: Pengurai faktur dan saran kode (JS murni)

**Files:**
- Create: `public/js/invoice-parser.js`, `public/js/similar-code.js`, `tests-js/fixtures/faktur-00066365.txt` (teks baris per baris dari faktur contoh), `tests-js/fixtures/faktur-00066365-ocr.txt` (salinan dengan salah baca)
- Test: `tests-js/invoice-parser.test.js`, `tests-js/similar-code.test.js`

**Interfaces:**
- Produces:
  - `parseInvoice(lines: string[]): { number: string, customer: string, date: string, items: {code, name, qty}[], warnings: string[] }` — aturan spec bagian 6.
  - `groupTextItems(items: {str, x, y}[], tolerance = 3): string[]` — gabung item teks pdf.js per baris (y mirip), urut x; baris urut y dari atas.
  - `editDistance(a: string, b: string): number`; `suggestCode(code: string, candidates: string[], maxDistance = 3): string | null`.

- [ ] **Step 1: Test gagal**:
```js
// fixture bersih → number '00066365', customer 'AKBAR JAYA MOTOR HSF', 9 items,
//   items[0] {code:'S-SCS-002', qty:2}, items[4].code 'H-MTE-009/010',
//   items[7].name diakhiri '(84306-BZ200)' (baris lanjutan digabung), items[8] {code:'S-EFP-EP-T11', qty:3}, warnings []
// fixture OCR (S-SCS-OO2, qty '2' terbaca 'Z') → tetap 9 items, warnings menyebut 'S-SCS-OO2' dan baris qty tak terbaca (qty null)
// tanpa 'No Faktur' → warning 'Nomor faktur tidak terbaca'
// groupTextItems: 3 item y=100 x acak + 1 item y=120 → 2 baris, urutan x benar
// editDistance('S-SCS-002','S-MCS-005') === 3 ; editDistance('abc','abc') === 0
// suggestCode('S-TKS-003', kodeFaktur) === 'S-TKS-002' ; suggestCode('ZZZ', kodeFaktur) === null
```
- [ ] **Step 2:** `npm test` → FAIL. **Step 3:** implementasi. **Step 4:** PASS.
- [ ] **Step 5: Commit** — `feat: offline invoice text parser and code suggestion`

---

### Task 6: Halaman admin — faktur baru

**Files:**
- Create: `resources/views/layouts/app.blade.php` (nav: Faktur baru · Gudang · Laporan), `resources/views/invoices/create.blade.php`, `public/css/app.css`, `public/js/api.js` (pindahan + header `X-CSRF-TOKEN` dari meta tag), `public/js/pages/invoice-create.js`, `public/vendor/pdfjs/` (`pdf.min.mjs`, `pdf.worker.min.mjs`), `public/vendor/tesseract/` (`tesseract.min.js`, `worker.min.js`, file `tesseract-core*` wasm, `eng.traineddata.gz`), route `GET /` → redirect `/gudang`, `GET /faktur/baru`

**Interfaces:**
- Consumes: `POST /api/invoices` (Task 2), `parseInvoice`, `groupTextItems` (Task 5).

Keputusan yang dikunci:
- Salin library dari `node_modules` (install sebagai devDependency versi persis di header) ke `public/vendor/` dan commit hasilnya.
- PDF: halaman 1 saja; `getTextContent()` → `groupTextItems` → `parseInvoice`. Pratinjau: render halaman 1 ke canvas.
- Foto: `Tesseract.createWorker('eng', 1, { workerPath, corePath, langPath })` semuanya menunjuk `public/vendor/tesseract/`; progres ditampilkan dalam persen.
- Tabel konfirmasi: kolom kode, nama, qty, hapus; tombol tambah baris; isian dengan peringatan diberi latar kuning lembut. Nomor & penerima wajib.
- Simpan → `FormData` (`file`, `payload`) → sukses: pesan "Faktur 00066365 tersimpan" + tautan ke sesi.

- [ ] **Step 1:** Implementasi.
- [ ] **Step 2: Verifikasi di browser** (`php artisan serve`): unggah `sparepart data/Faktur.jpeg` → progres OCR → tabel 9 baris tampil di samping gambar; perbaiki satu isian; simpan → muncul di `/api/invoices?status=open`. Ulangi simpan → diganti (belum ada scan).
- [ ] **Step 3: Commit** — `feat: invoice upload page with offline PDF/OCR reading`

---

### Task 7: Halaman gudang — daftar sesi dan sesi scan

**Files:**
- Create: `resources/views/warehouse/index.blade.php`, `resources/views/warehouse/session.blade.php`, `public/js/pages/warehouse-index.js`, `public/js/pages/warehouse-session.js`, `public/js/sound.js` (beep sukses 880 Hz, buzz error 220 Hz), route `GET /gudang`, `GET /gudang/sesi/{invoice}`

**Interfaces:**
- Consumes: `GET /api/invoices`, `GET /api/invoices/{id}`, `POST .../scans`, `POST .../complete` (Task 2–3), `createKeyboardWedge`, `suggestCode`, `formatWib`.

Keputusan yang dikunci:
- Daftar: tab "Perlu di-scan" (`open`) dan "Selesai" (`done`), kolom cari dengan debounce 300 ms, tiap kartu: nomor, penerima, waktu unggah, progres `picked_total/total_qty`.
- Sesi: header nomor kiri, penerima kanan, label tahap + progres. Tabel baris: hijau `✓` saat lengkap, abu-abu `n/qty` saat sebagian. Baris merah `✗` untuk `not_in_invoice` di bawah tabel (dari scan_events sesi ini), dengan teks saran `Mungkin maksudnya …?` dari `suggestCode`. `over_qty` → toast merah 3 detik.
- Scanner ditangkap `createKeyboardWedge` (abaikan saat fokus di input). Kotak "Ketik kode" di bawah, kirim dengan `manual: true`.
- Tombol "Selesai" hanya aktif saat semua lengkap; setelah `picked` muncul "Mulai scan QC" (ganti tahap tampilan ke `qc`). `qc_done` → tampilan terkunci, label "Selesai QC".
- Panel "Riwayat scan" (waktu WIB, tahap, kode, hasil, manual).
- Huruf besar untuk layar gudang: kode part 20 px mono, baris minimal 56 px.

- [ ] **Step 1:** Implementasi.
- [ ] **Step 2: Verifikasi di browser** dengan faktur dari Task 6, mensimulasikan scanner (event keydown cepat): kode benar → hijau; kode benar lagi pada qty 1 → toast `sudah lengkap`; `H-MTE-005` → baris merah dengan saran; Selesai nonaktif sampai semua hijau; selesaikan → muncul di tab Selesai; scan QC → `qc_done`.
- [ ] **Step 3: Commit** — `feat: warehouse session list and scan screen`

---

### Task 8: Halaman laporan, README, verifikasi akhir

**Files:**
- Create: `resources/views/reports/outgoing.blade.php`, `public/js/pages/report.js`, route `GET /laporan`, `README.md`

- [ ] **Step 1:** Halaman laporan: input tanggal dari/sampai (default hari ini), cari penerima, daftar faktur selesai dengan barangnya, tabel total per kode.
- [ ] **Step 2:** README (bahasa Indonesia): prasyarat (Laragon PHP 8.2 + MySQL, Composer, Node untuk test JS), `composer install`, buat database `sparepart`, `php artisan migrate`, `php artisan serve`, jadwal `php artisan schedule:work` (lokal) / cron `schedule:run` (hosting), cara pakai admin & gudang, catatan "belum ada login".
- [ ] **Step 3: Verifikasi.** `php artisan test` → semua PASS. `npm test` → semua PASS. `php artisan migrate:fresh` di MySQL Laragon sukses. Uji manual satu putaran penuh dengan scanner Newland asli bila tersedia (dicatat sebagai tugas user bila tidak).
- [ ] **Step 4: Commit** — `feat: outgoing report page and README`

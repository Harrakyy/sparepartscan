# Retur Barang Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gudang bisa mencatat barang yang dikembalikan pelanggan dengan scan barcode, dipisah bagus/jelek, plus tanda "sudah diinput ke Zahir" per retur.

**Architecture:** Tiga tabel baru (`goods_returns`, `goods_return_items`, `goods_return_scans`) dan satu `ReturnService` yang memegang semua aturan; controller tipis di `/api/returns`; tiga halaman Blade + ES module tanpa build, memakai ulang scanner, bunyi, dan kedip merah yang ada. Faktur asli tidak diubah.

**Tech Stack:** Laravel 12 (PHP 8.2), MySQL / SQLite (tes), PHPUnit feature tests, Blade + JS module biasa, bootstrap-icons.

**Spec:** `docs/superpowers/specs/2026-10-27-retur-barang-design.md`

## Global Constraints

- Semua teks UI dan pesan error berbahasa Indonesia.
- Amplop API `{success, data, error}` lewat `App\Support\ApiResponse`; pelanggaran aturan → `DomainRuleException` → HTTP 409.
- Rute API di `routes/api.php` (grup web + prefix `/api`, CSRF aktif).
- Nama model `GoodsReturn` (bukan `Return`, kata kunci PHP).
- Retur hanya untuk faktur berstatus `Invoice::DONE_STATUSES` (`picked`, `qc`, `qc_done`).
- Kondisi: `good` / `bad`. Hasil scan: `ok` / `over_qty` / `not_in_invoice`.
- Tidak ada `console.log` di kode produksi; tidak ada library baru.
- Daftar dibatasi 50 baris (pola `LIST_LIMIT` di `InvoiceController`).

## Review Focus

1. Scan cepat beruntun di dua tab untuk barang yang sama tidak boleh melewati sisa → hitung sisa di dalam transaksi dengan `lockForUpdate` pada faktur (Task 1, `test_returns_never_exceed_invoice_qty_across_returns`).
2. Kode diketik huruf kecil/berspasi (`" s-scs-002 "`) harus cocok seperti scan keluar → `strtoupper(trim())` (Task 1, `test_code_is_normalised`).
3. Faktur yang belum selesai scan pertama tidak muncul di pencarian dan ditolak saat dibuat (Task 1 & 2).
4. Membuka retur yang sudah `done` lalu scan/koreksi/ubah catatan harus ditolak 409, bukan diam-diam berubah (Task 1, `test_done_return_is_locked`).
5. Koreksi −1 di barang yang jumlahnya 0 ditolak dan tidak membuat baris negatif (Task 1, `test_decrement_below_zero_is_rejected`).

---

### Task 1: Data dan aturan retur

**Files:**
- Create: `database/migrations/2026_10_27_000001_create_goods_returns_tables.php` (ketiga tabel, kolom persis seperti spec bagian Data)
- Create: `app/Models/GoodsReturn.php`, `app/Models/GoodsReturnItem.php`, `app/Models/GoodsReturnScan.php`
- Modify: `app/Models/Invoice.php` — tambah `goodsReturns(): HasMany`
- Create: `app/Services/ReturnService.php`
- Test: `tests/Feature/ReturnServiceTest.php`

**Interfaces:**
- Produces (dipakai Task 2):
  - `GoodsReturn` — `$fillable = ['note','status','done_at','zahir_entered_at']`, cast `done_at`/`zahir_entered_at` datetime; relasi `invoice()`, `items()`, `scans()`.
  - `GoodsReturnItem` — `$fillable = ['invoice_item_id','condition','qty']`, timestamps biasa; relasi `invoiceItem()`.
  - `GoodsReturnScan` — seperti `ScanEvent`: `UPDATED_AT = null`, cast `manual` boolean.
  - `ReturnService`:
    - `open(Invoice $invoice): array{return: GoodsReturn, existing: bool}`
    - `scan(GoodsReturn $r, string $code, string $condition, bool $manual): array{result: string, message: ?string, item_id: ?int}`
    - `decrement(GoodsReturn $r, int $invoiceItemId, string $condition): void`
    - `updateNote(GoodsReturn $r, ?string $note): void`
    - `complete(GoodsReturn $r): void`
    - `cancel(GoodsReturn $r): void`
    - `setZahirEntered(GoodsReturn $r, bool $entered): void`
    - `detail(GoodsReturn $r): array` → `id, status, note, created_at, done_at, zahir_entered_at, invoice{id, number, customer_name, invoice_date}, items[{invoice_item_id, part_code, part_name, shipped, returned_before, good, bad, remaining}], scans[{code, condition, result, manual, created_at}]` (scan terbaru dulu)
    - `list(string $tab): Collection` — tab `open` | `done` | `zahir_pending` (done + `zahir_entered_at` null); baris `id, status, created_at, done_at, zahir_entered_at, invoice_number, customer_name, good_total, bad_total`; terbaru dulu, maks 50
    - `eligibleInvoices(string $q): Collection` — `id, number, customer_name, picked_at, open_return_id`; cari nomor/pelanggan (escape `%_\`), maks 20

Pesan tetap (copy persis):
- belum boleh retur: `Faktur ini belum selesai scan pertama`
- tidak ada di faktur: `{CODE} tidak ada di faktur`
- penuh: `{CODE} sudah diretur semua ({n}/{qty})`
- terkunci: `Retur ini sudah selesai`
- selesai kosong: `Belum ada barang yang diretur`
- batal berisi: `Retur yang sudah berisi barang tidak bisa dibatalkan`
- Zahir saat open: `Selesaikan retur dulu`
- −1 dari 0: `Tidak ada yang bisa dikurangi`

- [ ] **Step 1: Tulis tes yang gagal** — `ReturnServiceTest` (RefreshDatabase, faktur dari `InvoiceApiTest::sampleItems()` seperti `ScanApiTest::setUp`, status `picked`):
  - `test_open_requires_finished_first_scan` — faktur `waiting` → `DomainRuleException` pesan belum boleh retur.
  - `test_open_returns_existing_open_return` — `open()` dua kali → id sama, `existing` true kedua kali; setelah `complete` → id baru.
  - `test_scan_counts_by_condition` — scan S-SCS-002 good, good, bad (qty 2) → good 2 dulu, lalu bad ditolak `over_qty`; `detail` item `good=2, bad=0, remaining=0`.
  - `test_returns_never_exceed_invoice_qty_across_returns` — retur 1: S-SCS-002 good 1, complete; retur 2: bad 1 ok, bad lagi `over_qty` pesan `S-SCS-002 sudah diretur semua (2/2)`; retur 2 `returned_before=1`.
  - `test_unknown_code_is_not_in_invoice_and_logged` — hasil `not_in_invoice`, 1 baris di `goods_return_scans` dengan `invoice_item_id` null.
  - `test_code_is_normalised` — `" s-scs-002 "` → `ok`.
  - `test_decrement_below_zero_is_rejected` — tanpa scan, `decrement` → exception; setelah 1 scan good, `decrement` → baris item terhapus.
  - `test_done_return_is_locked` — setelah complete: scan, decrement, updateNote → exception `Retur ini sudah selesai`.
  - `test_complete_requires_an_item` dan `test_cancel_only_empty_open_return`.
  - `test_zahir_flag_only_for_done_and_can_be_cleared`.
  - `test_list_tabs_and_eligible_invoices` — retur open/done/done+Zahir masuk tab yang benar; faktur `waiting` tidak ada di `eligibleInvoices('')`; `open_return_id` terisi untuk faktur yang punya retur open.

- [ ] **Step 2: Jalankan** `php artisan test --filter=ReturnServiceTest` → FAIL (kelas belum ada).

- [ ] **Step 3: Implementasi** migrasi, model, `ReturnService`. Semua perubahan dalam `DB::transaction` dengan `Invoice::lockForUpdate()` pada faktur retur lalu muat ulang retur; sisa = `qty − SUM(goods_return_items.qty)` untuk `invoice_item_id` itu lintas semua retur faktur. Tambah 1 memakai `firstOrCreate` + `increment`; −1 memakai `decrement` lalu hapus bila 0.

- [ ] **Step 4: Jalankan** `php artisan test` → semua PASS.

- [ ] **Step 5: Commit** `feat: goods return data model and rules`

### Task 2: API retur

**Files:**
- Create: `app/Http/Controllers/Api/ReturnController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/ReturnApiTest.php`

**Interfaces:**
- Consumes: `ReturnService` (Task 1).
- Produces (dipakai Task 3–4), rute persis spec bagian API dengan parameter `{goodsReturn}`:
  - `GET /api/returns?tab=` → list; tab tak dikenal dianggap `open`
  - `GET /api/returns/invoices?q=` → eligible (didaftarkan **sebelum** `/api/returns/{goodsReturn}`)
  - `POST /api/returns` `{invoice_id}` → `201` `{...detail, existing: false}` atau `200` `{...detail, existing: true}`
  - `GET /api/returns/{id}` → detail
  - `PATCH /api/returns/{id}` `{note: nullable|string|max:500}` → detail
  - `POST /api/returns/{id}/scans` `{code: required|string|max:64, condition: in:good,bad, manual: boolean}` → `{scan, return: detail}`
  - `POST /api/returns/{id}/decrement` `{invoice_item_id: integer, condition}` → detail
  - `POST /api/returns/{id}/complete` → detail
  - `DELETE /api/returns/{id}` → `{deleted: true}`
  - `POST /api/returns/{id}/zahir` `{entered: boolean}` → detail
  - `DomainRuleException` → `ApiResponse::fail($msg, 409)`; validasi → 422 (handler yang ada).

- [ ] **Step 1: Tulis tes yang gagal** — `ReturnApiTest`:
  - `test_full_flow_create_scan_complete_and_mark_zahir` — POST create 201, scan ok, complete, zahir true → `data.zahir_entered_at` terisi, list `tab=zahir_pending` kosong.
  - `test_creating_again_returns_existing_open_return` — 200 dan `data.existing` true.
  - `test_waiting_invoice_cannot_get_a_return` — 409 `error` = `Faktur ini belum selesai scan pertama`.
  - `test_scan_validation_and_locked_return` — condition `x` → 422; scan setelah complete → 409.
  - `test_cancel_empty_return` — DELETE 200, lalu GET 404; DELETE retur berisi → 409.
  - `test_invoice_search_only_lists_eligible_invoices`.

- [ ] **Step 2: Jalankan** `php artisan test --filter=ReturnApiTest` → FAIL.
- [ ] **Step 3: Implementasi** controller + rute (ikuti pola `ScanController`).
- [ ] **Step 4: Jalankan** `php artisan test` → PASS.
- [ ] **Step 5: Commit** `feat: goods return API`

### Task 3: Menu, daftar retur, dan retur baru

**Files:**
- Modify: `resources/views/layouts/app.blade.php` — item nav `Retur` (`bi-arrow-return-left`) setelah Gudang, `aria-current` saat `request()->is('retur*')`
- Modify: `routes/web.php` — `Route::view('/retur', 'returns.index')`, `Route::view('/retur/baru', 'returns.create')`
- Create: `resources/views/returns/index.blade.php`, `public/js/pages/return-index.js`
- Create: `resources/views/returns/create.blade.php`, `public/js/pages/return-create.js`

**Interfaces:**
- Consumes: `GET /api/returns`, `GET /api/returns/invoices`, `POST /api/returns` (Task 2); `apiGet/apiPost` (`api.js`), `formatWib` (`format.js`).
- Produces: link ke `/retur/{id}` (Task 4).

- [ ] **Step 1:** Daftar mengikuti markup/kelas `warehouse/index.blade.php` (tab + count pill + tabel `grid`). Tab: `Sedang dicek` (open), `Selesai` (done), `Belum di Zahir` (zahir_pending). Kolom: Tanggal (`created_at`, WIB), No faktur, Kepada, Bagus, Jelek, Zahir (`✓ tanggal` / `Belum`). Baris diklik → `/retur/{id}`. Tombol `Retur baru` → `/retur/baru`.
- [ ] **Step 2:** Retur baru: kotak cari (debounce 300 ms) → tabel faktur (No faktur, Kepada, Dicek); tombol per baris `Buat retur` (atau `Lanjutkan retur` bila `open_return_id`) → POST → `location = /retur/{id}`.
- [ ] **Step 3: Verifikasi** `node --check` kedua file JS; buka kedua halaman di server tes, buat retur dari faktur `picked`, pastikan pindah ke `/retur/{id}` dan faktur `waiting` tidak muncul.
- [ ] **Step 4: Commit** `feat: return list and new-return pages`

### Task 4: Halaman scan retur

**Files:**
- Create: `public/js/flash.js` — `export function flashError(): void` (pindahan dari `warehouse-session.js`, elemen `#error-flash`)
- Modify: `public/js/pages/warehouse-session.js` — impor `flashError` dari `../flash.js`
- Modify: `routes/web.php` — `Route::get('/retur/{goodsReturn}', fn (GoodsReturn $goodsReturn) => view('returns.session', compact('goodsReturn')))`
- Create: `resources/views/returns/session.blade.php`, `public/js/pages/return-session.js`
- Modify: `public/css/app.css` — mode bar dan bingkai mode

**Interfaces:**
- Consumes: endpoint Task 2; `createKeyboardWedge` (`keyboard-wedge.js`), `beepOk/buzzError` (`sound.js`), `flashError` (`flash.js`).

- [ ] **Step 1:** Markup: kepala (No faktur, Kepada, `textarea#note` maxlength 500, simpan via PATCH saat `change`), dua tombol `#mode-good` "BAGUS" / `#mode-bad` "JELEK" (`aria-pressed`), scan bar + toast seperti sesi gudang, tabel (Kode, Nama, Dikirim, Diretur sebelumnya, Bagus [−1], Jelek [−1]), tombol `Selesai`, `Batalkan retur`, panel selesai dengan tombol Zahir.
- [ ] **Step 2:** Mode: default `good`; `body` dapat kelas `return-mode-good` / `return-mode-bad` → `box-shadow: inset 0 0 0 6px` hijau / merah (`--ok-solid` / `--bad-solid`). Klik tombol mode lalu fokus kembali ke kotak scan.
- [ ] **Step 3:** Scan: logika kotak scan, fokus otomatis, dan antrean seperti `warehouse-session.js` (Enter, kecepatan ketik membedakan manual). `ok` → `beepOk` + kedip baris; selain itu `buzzError` + `flashError` + toast pesan. −1 → POST decrement. Selesai/Batalkan (konfirmasi `confirm()` untuk batal) /Zahir → panggil endpoint lalu render ulang; batal → `location = /retur`.
- [ ] **Step 4: Verifikasi** `node --check`; `npm test`; `php artisan test`. Di server tes: scan bagus, ganti mode, scan jelek, kode salah (kedip merah), −1, selesai (terkunci), tandai Zahir, batalkan; sesi gudang masih berkedip merah saat salah scan.
- [ ] **Step 5: Commit** `feat: return scan page with good/bad modes`

### Task 5: Review akhir

- [ ] **Step 1:** `php artisan test` dan `npm test` → semua PASS.
- [ ] **Step 2:** Review seluruh diff fitur (satu reviewer) terhadap spec; perbaiki temuan CRITICAL/HIGH.
- [ ] **Step 3:** Commit perbaikan bila ada.

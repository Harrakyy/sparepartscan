# Sparepart Scan MVP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Web app internal gudang sparepart: scan barcode dengan kamera HP untuk cek info part, mencatat barang masuk, dan mencatat barang keluar.

**Architecture:** Satu proses Node.js (Express) melayani REST API JSON dan halaman statis dari `public/`. Data disimpan di SQLite lewat `node:sqlite`, dan akses data dibungkus repository kecil per tabel. Frontend memakai HTML + ES module tanpa build step. Kamera dibaca dengan `html5-qrcode`, dan HP mengakses laptop lewat Cloudflare Quick Tunnel (HTTPS).

**Tech Stack:** Node.js 24, Express 5, `node:sqlite`, `html5-qrcode` 2.3.8, `node:test`, cloudflared.

**Spec:** `docs/superpowers/specs/2026-10-22-sparepart-scan-mvp-design.md`

## Global Constraints

- Semua kode ES module (`"type": "module"` di `package.json`).
- Dependency runtime hanya `express` dan `html5-qrcode`. Tidak ada dependency dev; test memakai `node:test` dan `fetch` bawaan.
- Respons API selalu `{ "success": boolean, "data": any|null, "error": string|null }`.
- Nama field JSON API sama dengan nama kolom DB (snake_case): `barcode`, `part_number`, `description`, `price_retail`, `price_member`, `price_wholesale`, `stock`, `part_id`, `type`, `qty`, `stock_after`, `note`, `created_at`, `updated_at`.
- `barcode` selalu TEXT, tidak pernah dikonversi ke angka (nol di depan harus tetap ada).
- Batas validasi: barcode 1–64, part_number 1–64, description 1–500, note 0–200 karakter setelah trim; harga bilangan bulat 0–1.000.000.000; qty bilangan bulat 1–100.000; `type` hanya `in` atau `out`.
- Waktu disimpan ISO 8601 UTC (`new Date().toISOString()`), ditampilkan WIB (`Asia/Jakarta`).
- Semua query memakai parameter. Teks dari server dirender dengan `textContent`, tidak pernah `innerHTML`.
- Teks UI berbahasa Indonesia. Pesan error persis seperti di spec:
  - stok kurang: `Stok tidak cukup (stok: N)`
  - error server: `Terjadi kesalahan di server. Coba lagi.`
  - jaringan: `Tidak bisa terhubung ke server. Pastikan laptop dan tunnel masih berjalan.`
- Env: `PORT` (bawaan 3000), `DB_PATH` (bawaan `data/sparepart.db`).
- Commit memakai format conventional (`feat:`, `test:`, `docs:`, `chore:`), tanpa baris attribution.

## Review Focus

1. **Barcode dengan nol di depan** (`0012345678905`): harus tersimpan dan dicari persis sama. → test di Task 2.
2. **Pencarian berisi `%` atau `_`**: harus dicocokkan sebagai huruf biasa, bukan wildcard. → test di Task 2.
3. **Angka tidak valid dari form** (`1.5`, `"abc"`, negatif, string angka `"5000"`): ditolak 400 dengan pesan jelas, karena client wajib mengirim number. → test di Task 1.
4. **Dua kali keluar melebihi stok** (stok 3, keluar 2 lalu keluar 2): yang kedua 409, stok tetap 1, tidak ada movement kedua. → test di Task 3.
5. **Body JSON rusak atau path API yang tidak ada**: respons tetap envelope JSON (400/404), bukan halaman HTML Express. → test di Task 2.

---

## File Structure

| File | Tanggung jawab |
|---|---|
| `package.json` | metadata, script `start`, `test`, `coverage` |
| `src/db.js` | membuka SQLite dan membuat tabel |
| `src/validation.js` | validasi murni input part, movement, limit |
| `src/respond.js` | helper envelope respons |
| `src/repositories/parts.js` | query tabel `parts` |
| `src/repositories/movements.js` | transaksi stok + query `movements` |
| `src/routes/parts.js`, `src/routes/movements.js` | HTTP → validasi → repository |
| `src/app.js` | merakit Express app (dipakai server dan test) |
| `src/server.js` | titik masuk: env, buka DB, listen |
| `public/js/format.js` | format Rupiah dan WIB (dipakai browser dan test) |
| `public/js/api.js` | fetch wrapper bersama |
| `public/js/scan.js`, `parts.js`, `history.js` | logika tiap halaman |
| `public/*.html`, `public/css/style.css` | markup dan gaya |
| `test/helpers.js` | menyalakan app dengan DB `:memory:` di port acak |

---

### Task 1: Scaffold, database, dan validasi

**Files:**
- Create: `package.json`, `src/db.js`, `src/validation.js`, `src/respond.js`
- Test: `test/validation.test.js`, `test/db.test.js`

**Interfaces:**
- Produces:
  - `openDb(path: string): DatabaseSync`: membuat tabel `parts` dan `movements` sesuai spec bagian 6 bila belum ada, menyalakan `PRAGMA foreign_keys = ON`, dan `PRAGMA journal_mode = WAL` kecuali path `:memory:`. Index: `UNIQUE(barcode)`, index `movements(created_at)`. `CHECK (stock >= 0)` pada `parts.stock`, `CHECK (type IN ('in','out'))` pada `movements.type`.
  - `validatePartCreate(body): { ok: true, value } | { ok: false, error: string }`: `value` berisi `barcode, part_number, description, price_retail, price_member, price_wholesale` (teks sudah di-trim).
  - `validatePartUpdate(body)`: sama, tanpa `barcode`. Field `barcode` atau `stock` di body diabaikan.
  - `validateMovement(body)`: `value` berisi `part_id` (integer ≥ 1), `type`, `qty`, `note` (string, `""` bila kosong).
  - `parseLimit(raw: string|undefined, def: number, max: number): number`: bukan angka atau < 1 → `def`; > max → `max`.
  - `ok(res, data, status = 200)` dan `fail(res, status, message)` di `src/respond.js`.

- [ ] **Step 1: Buat `package.json`**

```json
{
  "name": "sparepart-scan",
  "version": "0.1.0",
  "private": true,
  "type": "module",
  "scripts": {
    "start": "node src/server.js",
    "test": "node --test",
    "coverage": "node --test --experimental-test-coverage --test-coverage-include=src/**/*.js --test-coverage-include=public/js/format.js --test-coverage-lines=80"
  }
}
```

Lalu `npm install express@5.1.0 html5-qrcode@2.3.8`.

- [ ] **Step 2: Tulis test yang gagal**

`test/validation.test.js`, satu `test()` per baris:

```js
validatePartCreate({ barcode: '  0012345678905 ', part_number: 'BP-01', description: 'Kampas rem', price_retail: 150000, price_member: 140000, price_wholesale: 130000 })
  // → ok:true, value.barcode === '0012345678905'
validatePartCreate({ ...valid, barcode: '' })                 // ok:false
validatePartCreate({ ...valid, barcode: 'x'.repeat(65) })     // ok:false
validatePartCreate({ ...valid, description: 'x'.repeat(501) })// ok:false
validatePartCreate({ ...valid, price_retail: 1.5 })           // ok:false
validatePartCreate({ ...valid, price_retail: '5000' })        // ok:false
validatePartCreate({ ...valid, price_retail: -1 })            // ok:false
validatePartCreate({ ...valid, price_retail: 1_000_000_001 }) // ok:false
validatePartUpdate({ ...validWithoutBarcode, barcode: 'X', stock: 99 }) // ok:true, value has no barcode/stock keys
validateMovement({ part_id: 1, type: 'in', qty: 5 })          // ok:true, value.note === ''
validateMovement({ part_id: 1, type: 'adjust', qty: 5 })      // ok:false
validateMovement({ part_id: 1, type: 'out', qty: 0 })         // ok:false
validateMovement({ part_id: 1, type: 'out', qty: 100_001 })   // ok:false
validateMovement({ part_id: 1, type: 'out', qty: 1, note: 'x'.repeat(201) }) // ok:false
validateMovement(null)                                        // ok:false, tidak throw
parseLimit(undefined, 100, 500) === 100; parseLimit('abc', 100, 500) === 100
parseLimit('0', 100, 500) === 100; parseLimit('999', 100, 500) === 500; parseLimit('20', 100, 500) === 20
```

`test/db.test.js`: `openDb(':memory:')` lalu `SELECT name FROM sqlite_master WHERE type='table'` berisi `parts` dan `movements`. Memanggil `openDb` dua kali pada file sementara (`os.tmpdir()`) tidak error.

- [ ] **Step 3: Jalankan, pastikan gagal**

Run: `npm test` → FAIL (modul belum ada).

- [ ] **Step 4: Implementasikan `src/db.js`, `src/validation.js`, `src/respond.js`**

Pesan error validasi menyebut nama field dalam bahasa Indonesia, misalnya `Part number wajib diisi (maks. 64 karakter)` dan `Harga umum harus bilangan bulat 0–1.000.000.000`.

- [ ] **Step 5: Jalankan, pastikan lulus**

Run: `npm test` → semua PASS. Boleh ada `ExperimentalWarning` dari `node:sqlite`.

- [ ] **Step 6: Commit**

```bash
git add package.json package-lock.json src test
git commit -m "feat: add database schema and input validation"
```

---

### Task 2: API part

**Files:**
- Create: `src/repositories/parts.js`, `src/routes/parts.js`, `src/app.js`, `test/helpers.js`
- Test: `test/parts.api.test.js`

**Interfaces:**
- Consumes: `openDb`, `validatePartCreate`, `validatePartUpdate`, `ok`, `fail` (Task 1).
- Produces:
  - `createPartsRepo(db)` → `{ findByBarcode(barcode): Part|undefined, findById(id): Part|undefined, search(q: string, limit = 50): Part[], create(input): Part, update(id, input): Part|undefined }`. `create` melempar `Error` dengan `code = 'DUPLICATE_BARCODE'` bila barcode sudah ada. `Part` = satu baris lengkap tabel `parts`.
  - `createApp({ db }): express.Application`: memasang `express.json({ limit: '10kb' })`, `/api/parts`, `/api/movements` (Task 3 menambahkannya), static `public/`, `/vendor/html5-qrcode.min.js`, fallback 404 JSON untuk `/api/*`, dan error handler JSON.
  - `startTestServer(): Promise<{ baseUrl: string, db, close(): Promise<void> }>` di `test/helpers.js` (DB `:memory:`, `listen(0)`).

- [ ] **Step 1: Tulis test yang gagal** (`test/parts.api.test.js`, server baru per test)

```js
// POST /api/parts valid → 201, data.stock === 0, data.barcode === '0012345678905'
// POST barcode sama lagi → 409, success false, error menyebut barcode sudah terdaftar
// POST harga 1.5 → 400
// GET /api/parts/by-barcode/0012345678905 → 200, data.part_number === 'BP-01'
// GET /api/parts/by-barcode/12345678905 → 404   (tanpa nol depan = barcode lain)
// GET /api/parts?q=kampas → menemukan part berdeskripsi 'Kampas rem' (case-insensitive)
// GET /api/parts?q=50% → hanya part yang deskripsinya mengandung '50%' literal, bukan semua part
// GET /api/parts?q=a_b → tidak cocok dengan 'axb'
// GET /api/parts (tanpa q) → maksimal 50 baris, urut part_number
// PUT /api/parts/:id ubah description → 200, barcode & stock tidak berubah
// PUT /api/parts/9999 → 404
// POST /api/parts dengan body 'not json' (Content-Type application/json) → 400 envelope JSON
// GET /api/tidak-ada → 404 envelope JSON
```

- [ ] **Step 2: Jalankan, pastikan gagal** — `npm test` → FAIL.

- [ ] **Step 3: Implementasikan repository, routes, dan `createApp`**

Pencarian:
```sql
SELECT * FROM parts
WHERE barcode LIKE ? ESCAPE '\' OR part_number LIKE ? ESCAPE '\' OR description LIKE ? ESCAPE '\'
ORDER BY part_number LIMIT ?
```
Parameter: `'%' + q.replace(/[\\%_]/g, '\\$&') + '%'`.

Duplikat dideteksi dari pesan SQLite `UNIQUE constraint failed: parts.barcode`. Route `/vendor/html5-qrcode.min.js` mengirim file dari `node_modules/html5-qrcode/`. Periksa nama file minified yang benar di folder itu setelah `npm install`. Error handler: `err.type === 'entity.parse.failed'` → 400 `Format data tidak valid`; selain itu `console.error(err)` lalu 500 dengan pesan server dari Global Constraints.

- [ ] **Step 4: Jalankan, pastikan lulus** — `npm test` → PASS.

- [ ] **Step 5: Commit** — `git commit -m "feat: add parts API"`

---

### Task 3: API pergerakan stok

**Files:**
- Create: `src/repositories/movements.js`, `src/routes/movements.js`
- Modify: `src/app.js` (pasang `/api/movements`)
- Test: `test/movements.api.test.js`

**Interfaces:**
- Consumes: `validateMovement`, `parseLimit`, `createPartsRepo` (untuk membuat data di test), `startTestServer`.
- Produces:
  - `createMovementsRepo(db)` → `{ record({ part_id, type, qty, note }): { movement: Movement, part: Part }, recent(limit: number): MovementRow[] }`.
  - `record` melempar `Error` dengan `code = 'PART_NOT_FOUND'`, atau `code = 'INSUFFICIENT_STOCK'` dan `stock: number`.
  - `MovementRow` = kolom `movements` + `part_number` + `description` (JOIN), urut `created_at DESC, id DESC`.
  - `POST /api/movements` → 201 `data: { movement, part }`.

- [ ] **Step 1: Tulis test yang gagal**

```js
// part stok 0; POST {type:'in', qty:5} → 201, data.part.stock === 5, data.movement.stock_after === 5
// lalu {type:'out', qty:2} → 201, stock 3
// lalu {type:'out', qty:10} → 409, error === 'Stok tidak cukup (stok: 3)', stok tetap 3
// part baru, in 3; out 2 → 201 (stok 1); out 2 → 409; GET /api/movements → tepat 2 baris (in 3, out 2), stok 1
// part_id 9999 → 404
// qty 0 → 400
// GET /api/movements?limit=1 → 1 baris, yang terbaru, ada part_number
// GET /api/movements?limit=9999 → tidak error (dipotong ke 500)
```

- [ ] **Step 2: Jalankan, pastikan gagal** — `npm test` → FAIL.

- [ ] **Step 3: Implementasikan `record` sebagai satu transaksi**

`BEGIN IMMEDIATE`. Untuk `out`: `UPDATE parts SET stock = stock - ?, updated_at = ? WHERE id = ? AND stock >= ?`. Kalau `changes === 0`, baca part: tidak ada → `PART_NOT_FOUND`, ada → `INSUFFICIENT_STOCK`. Untuk `in`: `stock = stock + ?`. Lalu `INSERT` movement dengan `stock_after`, kemudian `COMMIT`. Di `catch`: `ROLLBACK` lalu lempar ulang.

- [ ] **Step 4: Jalankan, pastikan lulus** — `npm test` → PASS.

- [ ] **Step 5: Commit** — `git commit -m "feat: add stock movement API"`

---

### Task 4: Server, kerangka frontend, dan helper format

**Files:**
- Create: `src/server.js`, `public/js/format.js`, `public/js/api.js`, `public/css/style.css`
- Test: `test/format.test.js`

**Interfaces:**
- Consumes: `createApp`, `openDb`.
- Produces:
  - `formatRupiah(n: number): string`: `Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })`.
  - `formatWib(iso: string): string`: hasilnya `DD/MM/YYYY HH:mm WIB`, dirakit dari `formatToParts` dengan `timeZone: 'Asia/Jakarta'` dan `hourCycle: 'h23'`.
  - `apiGet(path)`, `apiPost(path, body)`, `apiPut(path, body)`: mengembalikan `data` kalau `success`. Kalau gagal, melempar `Error(error)` dengan properti `status`. Kegagalan `fetch` melempar `Error` berisi pesan jaringan dari Global Constraints dengan `status = 0`.
  - CSS: navigasi bawah tetap `.tabbar` berisi tautan Scan / Part / Riwayat. Kelas `.card`, `.btn`, `.btn-primary`, `.field`, `.msg-error`, `.msg-ok`. Lebar ramah HP, target sentuh minimal 44px.

- [ ] **Step 1: Tulis test yang gagal** (`test/format.test.js`)

```js
assert.equal(formatRupiah(150000).replace(/\s/g, ' '), 'Rp 150.000');
assert.equal(formatRupiah(0).replace(/\s/g, ' '), 'Rp 0');
assert.equal(formatWib('2026-10-22T07:05:00.000Z'), '22/10/2026 14:05 WIB');
assert.equal(formatWib('2026-10-22T17:30:00.000Z'), '23/10/2026 00:30 WIB');
```

- [ ] **Step 2: Jalankan, pastikan gagal** — `npm test` → FAIL.

- [ ] **Step 3: Implementasikan `format.js`, `api.js`, `style.css`, `server.js`**

`server.js` membuat folder induk `DB_PATH` (`fs.mkdirSync(..., { recursive: true })`), lalu `listen(PORT)` dan mencetak `Server jalan di http://localhost:PORT`.

- [ ] **Step 4: Jalankan test dan server**

Run: `npm test` → PASS. Run: `npm start` lalu `curl http://localhost:3000/api/parts` → `{"success":true,"data":[],"error":null}`. Hentikan server.

- [ ] **Step 5: Commit** — `git commit -m "feat: add server entry and shared frontend helpers"`

---

### Task 5: Halaman Scan

**Files:**
- Create: `public/index.html`, `public/js/scan.js`

**Interfaces:**
- Consumes: `apiGet`, `apiPost`, `formatRupiah`, `/vendor/html5-qrcode.min.js` (global `Html5Qrcode`, `Html5QrcodeSupportedFormats`).

Perilaku persis seperti spec bagian 5.1. Keputusan yang dikunci:
- Mode tersimpan di `localStorage['scanMode']` (`check` | `in` | `out`, bawaan `check`). Akses dibungkus `try/catch`.
- Kamera: `new Html5Qrcode('reader').start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 260, height: 160 }, formatsToSupport: [EAN_13, EAN_8, UPC_A, UPC_E, CODE_128, CODE_39, QR_CODE] }, onScan)`.
- `onScan`: abaikan barcode yang sama dalam 2000 ms, `navigator.vibrate?.(80)`, `scanner.pause(true)`, lalu `lookup(barcode)`. **Scan lagi** → `scanner.resume()`.
- Kamera gagal start → tampilkan `Kamera tidak bisa dibuka. Izinkan akses kamera di pengaturan browser, atau ketik barcode di bawah.` Input manual tetap aktif.
- `lookup`: 200 → kartu part. 404 → form daftar (barcode readonly). Setelah daftar berhasil di mode `in`, langsung tampilkan kartu dengan form jumlah.
- Simpan masuk/keluar → `POST /api/movements` lalu tampilkan `Stok sekarang: N`. Error 409 → tampilkan `error` dari server apa adanya.
- Tombol simpan dinonaktifkan selama request berjalan (mencegah dobel kirim).

- [ ] **Step 1: Implementasikan halaman**

- [ ] **Step 2: Verifikasi di browser laptop** (kamera boleh tanpa HTTPS di `localhost`)

`npm start`, buka `http://localhost:3000`, lalu pakai input manual:
1. Barcode `8991234567890` → form daftar → isi lalu simpan.
2. Mode Masuk, jumlah 5 → `Stok sekarang: 5`.
3. Mode Keluar, jumlah 10 → `Stok tidak cukup (stok: 5)`.
4. Mode Keluar, jumlah 2 → `Stok sekarang: 3`.
5. Muat ulang halaman → mode terakhir masih terpilih.
6. Lebar 375px: tidak ada scroll horizontal.

- [ ] **Step 3: Commit** — `git commit -m "feat: add scan page with check, in, and out modes"`

---

### Task 6: Halaman Daftar Part dan Riwayat

**Files:**
- Create: `public/parts.html`, `public/js/parts.js`, `public/history.html`, `public/js/history.js`

**Interfaces:**
- Consumes: `apiGet`, `apiPut`, `formatRupiah`, `formatWib`.

Keputusan yang dikunci:
- Pencarian part memakai debounce 300 ms ke `GET /api/parts?q=`, dan memuat daftar awal tanpa `q`.
- Ketuk baris → form edit di halaman yang sama (part number, deskripsi, 3 harga). Barcode dan stok tampil read-only. Simpan → `PUT`, lalu daftar dimuat ulang dan muncul pesan `Tersimpan`.
- Riwayat: `GET /api/movements?limit=100`. Kolom: waktu (`formatWib`), `Masuk`/`Keluar`, part number, jumlah, catatan. Keadaan kosong: `Belum ada pergerakan barang.`

- [ ] **Step 1: Implementasikan kedua halaman**

- [ ] **Step 2: Verifikasi di browser**

Dengan data dari Task 5: cari `kampas` → part muncul. Ubah deskripsi → tersimpan, stok tidak berubah. Riwayat → 2 baris (Masuk 5, Keluar 2) dengan jam WIB yang benar. Lebar 375px tanpa scroll horizontal.

- [ ] **Step 3: Commit** — `git commit -m "feat: add parts list and movement history pages"`

---

### Task 7: README, coverage, dan uji di HP

**Files:**
- Create: `README.md`

- [ ] **Step 1: Tulis `README.md`** (bahasa Indonesia)

Isinya: prasyarat (Node 24, cloudflared), `npm install`, `npm start`, menjalankan tunnel `cloudflared tunnel --url http://localhost:3000` (cara install cloudflared di Windows: `winget install --id Cloudflare.cloudflared`), `npm test`, `npm run coverage`, peringatan keamanan dari spec bagian 10, dan daftar uji manual dari spec bagian 11.

- [ ] **Step 2: Cek coverage**

Run: `npm run coverage` → semua test PASS dan line coverage ≥ 80% (perintah gagal kalau di bawah 80%).

- [ ] **Step 3: Uji manual di HP**

Jalankan server dan tunnel, lalu ikuti 8 langkah uji manual di spec bagian 11 dengan barang asli. Catat hasil per langkah (lolos/gagal) di pesan ke user.

- [ ] **Step 4: Commit** — `git commit -m "docs: add README with run and tunnel instructions"`

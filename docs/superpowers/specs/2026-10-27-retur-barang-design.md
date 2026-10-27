# Retur barang — desain

Tanggal: 2026-10-27
Status: disetujui lewat diskusi, menunggu review spec

## Tujuan

Barang yang sudah dicek, dicetak, dan dikirim bisa kembali ke gudang karena masalah.
Gudang mencatat barang yang kembali dengan scan barcode dan memisahkannya:

- **Bagus** — boleh masuk stok lagi.
- **Jelek** — tidak masuk stok.

Stok resmi tetap di **Zahir**. App ini tidak menyimpan angka stok; app mencatat retur
per barang dan kondisinya, lalu admin menginput retur di Zahir dan menandainya di app.
Integrasi API Zahir belum disepakati (biaya tambahan); tanda "sudah diinput ke Zahir"
per retur menjadi titik sambung bila nanti dikirim otomatis.

**Berhasil bila:** petugas bisa mencatat retur dari faktur yang sudah dicek hanya dengan
scanner, jumlah retur tidak pernah melebihi jumlah di faktur, dan admin bisa melihat retur
mana yang belum diinput ke Zahir.

## Keputusan

| Hal | Keputusan |
|---|---|
| Asal retur | Selalu dari faktur yang ada di app dan scan pertamanya sudah selesai |
| Cara input | Scan barcode (ketik manual + Enter tetap bisa, seperti scan keluar) |
| Bagus/jelek | Pilih mode BAGUS/JELEK dulu, lalu scan |
| Alasan | Satu catatan teks per retur, opsional |
| Pendekatan | Catatan retur tersendiri; faktur asli tidak diubah |
| Stok | Tidak ada angka stok di app; tanda "sudah diinput ke Zahir" per retur |

## Data

Nama model memakai `GoodsReturn` karena `return` adalah kata kunci PHP.

**`goods_returns`** — satu kejadian retur.

| Kolom | Tipe | Catatan |
|---|---|---|
| id | bigint | |
| invoice_id | FK invoices | cascade delete |
| note | varchar(500) null | alasan retur |
| status | enum open, done | default open, diindeks |
| done_at | timestamp null | |
| zahir_entered_at | timestamp null | kosong = belum diinput ke Zahir |
| timestamps | | |

**`goods_return_items`** — jumlah per barang per kondisi dalam satu retur.

| Kolom | Tipe | Catatan |
|---|---|---|
| id | bigint | |
| goods_return_id | FK goods_returns | cascade delete |
| invoice_item_id | FK invoice_items | |
| condition | enum good, bad | |
| qty | unsigned int | ≥ 1; baris dihapus bila turun ke 0 |
| unique | (goods_return_id, invoice_item_id, condition) | |

**`goods_return_scans`** — riwayat setiap scan (audit).

| Kolom | Tipe | Catatan |
|---|---|---|
| id | bigint | |
| goods_return_id | FK goods_returns | cascade delete |
| invoice_item_id | FK invoice_items null | null bila kode tidak ada di faktur |
| code | varchar(64) | |
| condition | enum good, bad | mode saat scan |
| result | enum ok, over_qty, not_in_invoice | |
| manual | boolean | diketik, bukan dari scanner |
| created_at | timestamp | |

## Aturan

1. Retur hanya untuk faktur berstatus `picked`, `qc`, atau `qc_done`.
2. Satu faktur paling banyak punya **satu retur `open`**. Membuat retur untuk faktur yang
   masih punya retur open mengembalikan retur open itu, bukan membuat yang baru.
   Retur berikutnya boleh dibuat setelah yang lama selesai.
3. Sisa yang bisa diretur per barang = `invoice_items.qty` − total `goods_return_items.qty`
   barang itu di **semua** retur faktur tersebut (bagus + jelek, open + done).
4. Scan: kode tidak ada di faktur → `not_in_invoice`; sisa 0 → `over_qty`; selain itu
   `ok` dan jumlah kondisi aktif bertambah 1. Semua hasil dicatat di `goods_return_scans`.
   Penghitungan dilakukan di dalam transaksi dengan `lockForUpdate` pada baris faktur,
   sehingga dua scan bersamaan tidak bisa melewati batas.
5. Koreksi −1 per barang per kondisi hanya selama retur `open`.
6. Selesai butuh minimal satu barang; setelah `done`, retur terkunci (tidak bisa scan,
   koreksi, atau ubah catatan).
7. Retur `open` yang masih kosong boleh dibatalkan (dihapus). Retur berisi atau `done`
   tidak bisa dihapus.
8. Tanda Zahir hanya untuk retur `done`; bisa diset dan dibatalkan.
9. Faktur asli (status, `picked_qty`, `qc_qty`) dan laporan barang keluar tidak berubah.

Pelanggaran aturan memakai `DomainRuleException` (HTTP 409) dengan pesan Bahasa Indonesia,
mengikuti pola yang sudah ada.

## API

Semua di grup `/api` yang ada (middleware web, CSRF), amplop `{success, data, error}`.

| Method | Path | Fungsi |
|---|---|---|
| GET | /api/returns?tab=open\|done\|zahir_pending | daftar retur |
| GET | /api/returns/invoices?q= | cari faktur yang bisa diretur (nomor/pelanggan), sertakan `open_return_id` |
| POST | /api/returns | `{invoice_id}` → retur baru, atau retur open yang sudah ada (`existing: true`) |
| GET | /api/returns/{id} | detail: retur, faktur, barang (dikirim, diretur sebelumnya, bagus, jelek di retur ini, sisa), riwayat scan |
| PATCH | /api/returns/{id} | `{note}` (hanya open) |
| POST | /api/returns/{id}/scans | `{code, condition, manual}` → `{scan: {result, message, item_id}, return: detail}` |
| POST | /api/returns/{id}/decrement | `{invoice_item_id, condition}` → detail |
| POST | /api/returns/{id}/complete | → detail |
| DELETE | /api/returns/{id} | batalkan retur open yang kosong |
| POST | /api/returns/{id}/zahir | `{entered: bool}` → detail |

## Layar

Menu baru **Retur** di topbar setelah Gudang (ikon `bi-arrow-return-left`).

- **`/retur`** — tabel ala daftar sesi Gudang. Tab: Sedang dicek, Selesai, Belum di Zahir.
  Kolom: tanggal, no faktur, kepada, bagus, jelek, status Zahir. Tombol "Retur baru".
- **`/retur/baru`** — kotak cari faktur (nomor/pelanggan, hanya yang bisa diretur);
  pilih faktur → "Buat retur" → ke halaman scan. Faktur dengan retur open langsung membuka
  retur itu.
- **`/retur/{id}`** — halaman scan:
  - Kepala: no faktur, kepada, kolom catatan (disimpan saat keluar dari kolom).
  - Dua tombol mode besar BAGUS (hijau) / JELEK (merah); bingkai halaman berwarna sesuai
    mode. Mode default BAGUS dan diingat per halaman.
  - Kotak scan selalu aktif (memakai `keyboard-wedge.js` seperti sesi gudang).
  - Tabel: Kode, Nama, Dikirim, Diretur sebelumnya, Bagus, Jelek; tombol −1 di samping
    angka Bagus/Jelek selama open.
  - Cocok: angka bertambah, baris berkedip, bunyi ok. Ditolak: kedip merah layar + alarm +
    pesan (memakai `flashError`, `beepOk`, `buzzError` yang ada).
  - Tombol Selesai (aktif bila ≥1 barang); Batalkan retur (hanya bila kosong).
  - Setelah selesai: ringkasan terkunci + tombol "Tandai sudah diinput ke Zahir" /
    "Diinput ke Zahir {tanggal} · Batalkan".

## Kode yang dipakai ulang

`keyboard-wedge.js`, `sound.js`, `api.js`, `format.js`, kedip merah (`.error-flash`),
gaya tabel/tab/count-pill di `app.css`. Kedip merah dan antrean scan dipindah ke modul
bersama bila perlu supaya tidak disalin.

## Pengujian

- Feature test PHP (`tests/Feature/ReturnApiTest.php`): pembuatan (status faktur, retur open
  ganda), scan ok/over_qty/not_in_invoice, batas sisa lintas beberapa retur dan kondisi,
  koreksi −1, selesai (kosong ditolak, terkunci setelahnya), batal (hanya kosong & open),
  tanda Zahir (hanya done), catatan, daftar per tab, pencarian faktur.
- Uji di browser: alur penuh retur di server tes.

## Di luar cakupan

Cetak struk retur, badge di menu Retur, angka stok di app, integrasi API Zahir, login.

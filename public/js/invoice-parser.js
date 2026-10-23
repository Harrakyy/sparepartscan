// Mengurai teks faktur penjualan Zahir (dari PDF atau OCR) menjadi data terstruktur.
// Fungsi murni: tidak menyentuh DOM, supaya bisa diuji dengan node --test.

// Kode part: huruf/angka, strip, lalu minimal dua segmen (contoh S-SCS-002, H-MTE-009/010, S-EFP-EP-T11).
const CODE_TOKEN = /^[A-Z0-9]-[A-Z0-9]+(?:-[A-Z0-9/]+)+$/i;
// OCR foto kertas continuous form sering menambah "noda" pendek di awal baris (®, PY, tr) dari lubang kertas.
const MAX_NOISE_TOKENS = 3;
const MAX_NOISE_LENGTH = 2;
// "Pes" = "Pcs" yang salah baca OCR.
const UNITS = 'Pcs|Pes|Pc|Set|Unit|Box|Dus|Psg|Pasang|Btl|Lbr|Roll|Mtr';
// Baris barang Zahir diakhiri "<jumlah> <satuan> <harga> <disc> <subtotal>". Jumlah diambil dari
// pola di akhir baris (boleh diikuti noda OCR pendek), sehingga angka di dalam nama tidak terambil.
const QTY_AT_END = /^(.*)\s+(\S+)\s+[A-Za-z]{2,8}\s+[\d.,]+\s+[\d.,]+\s+[\d.,]+(?:\s+\S{1,3})*\s*$/;
// Cadangan untuk baris tanpa kolom harga lengkap.
const QTY_BEFORE_UNIT = new RegExp(`^(.*)\\s+(\\S+)\\s+(?:${UNITS})\\b`, 'i');
const ITEMS_HEADER = /Nama\s+Barang/i;
const ITEMS_FOOTER = /Dasar\s+Pengenaan|\bJuta\b|\bRibu\b|\bRatus\b|Apabila|PENERIMA|Total\s*:/i;
// Label lain yang bisa berada di baris yang sama dengan "Kepada" atau "Tanggal".
const TRAILING_LABEL = /\s+(?:Up\.?|Tanggal|Term|Alamat|NPWP|No\.?\s*Faktur)\s*:.*$/i;
const MAX_QTY = 10000;

function labelValue(lines, label) {
  for (const line of lines) {
    const m = line.match(new RegExp(`${label}\\s*:?\\s*(.+)$`, 'i'));
    if (m) return m[1].replace(TRAILING_LABEL, '').replace(/[^\p{L}\p{N})]+$/u, '').trim();
  }
  return '';
}

// "No Faktur : 00066365"; OCR bisa membacanya "Nofakiur : 00066365".
function invoiceNumber(lines) {
  for (const line of lines) {
    const m = line.match(/\bNo\.?\s*[a-z]{4,8}\s*:\s*(\d{4,32})\b/i);
    if (m) return m[1];
  }
  return '';
}

// Mengembalikan [kode, sisa baris] bila baris diawali kode part (boleh didahului noda pendek).
function splitCodeLine(line) {
  const tokens = line.split(' ');
  for (let i = 0; i <= MAX_NOISE_TOKENS && i < tokens.length; i++) {
    if (CODE_TOKEN.test(tokens[i])) return [tokens[i].toUpperCase(), tokens.slice(i + 1).join(' ')];
    if (tokens[i].length > MAX_NOISE_LENGTH) return null;
  }
  return null;
}

// Baris lanjutan nama (contoh "(84306-BZ200)") harus berisi teks nyata, bukan sisa noda seperti "0, '".
const isMeaningful = line => /[\p{L}\p{N}]{3,}/u.test(line);

// Huruf O di segmen yang seharusnya angka (contoh "OO2") biasanya salah baca dari 0.
function looksMisread(code) {
  return code.split('-').some(seg => /^[0-9O/]+$/.test(seg) && seg.includes('O'));
}

function itemSection(lines) {
  const start = lines.findIndex(l => ITEMS_HEADER.test(l));
  if (start === -1) return { section: lines, joinContinuations: false };
  const rest = lines.slice(start + 1);
  // Baris berkode part tidak pernah dianggap penutup, walau namanya memuat kata seperti "RATUS".
  const end = rest.findIndex(l => ITEMS_FOOTER.test(l) && !splitCodeLine(l));
  return { section: end === -1 ? rest : rest.slice(0, end), joinContinuations: true };
}

function parseItemLine(code, rest) {
  const m = rest.match(QTY_AT_END) ?? rest.match(QTY_BEFORE_UNIT);
  if (!m) return { code, name: rest.trim(), qty: null };
  const qty = /^\d+$/.test(m[2]) ? Number(m[2]) : null;
  return { code, name: m[1].trim(), qty };
}

/**
 * @param {string[]} lines baris teks dari atas ke bawah
 * @returns {{number: string, customer: string, date: string, items: {code: string, name: string, qty: number|null}[], warnings: string[]}}
 */
export function parseInvoice(lines) {
  const clean = lines.map(l => String(l).replace(/\s+/g, ' ').trim()).filter(Boolean);
  const warnings = [];
  const number = invoiceNumber(clean);
  const customer = labelValue(clean, 'Kepada');
  const date = labelValue(clean, 'Tanggal');
  if (!number) warnings.push('Nomor faktur tidak terbaca');
  if (!customer) warnings.push('Nama penerima tidak terbaca');

  const { section, joinContinuations } = itemSection(clean);
  const items = [];
  for (const line of section) {
    const split = splitCodeLine(line);
    if (split) {
      items.push(parseItemLine(...split));
    } else if (QTY_AT_END.test(line)) {
      // Berbentuk baris barang tapi kodenya tidak terbaca (misalnya "$-MCS-005"): jangan digabung ke nama.
      warnings.push(`Baris tidak dikenali, periksa dan tambahkan manual: ${line}`);
    } else if (joinContinuations && items.length && isMeaningful(line)) {
      const last = items[items.length - 1];
      last.name = `${last.name} ${line}`.trim();
    }
  }

  for (const item of items) {
    if (looksMisread(item.code)) warnings.push(`Kode ${item.code} mungkin salah baca (huruf O dan angka 0)`);
    if (item.qty === null) warnings.push(`Jumlah untuk ${item.code} tidak terbaca`);
    else if (item.qty < 1 || item.qty > MAX_QTY) warnings.push(`Jumlah untuk ${item.code} tidak wajar (${item.qty})`);
  }
  if (!items.length) warnings.push('Tidak ada barang yang terbaca');

  return { number, customer, date, items, warnings };
}

/**
 * Menggabungkan potongan teks pdf.js menjadi baris. `y` memakai koordinat PDF
 * (nilai lebih besar = lebih atas), jadi baris diurutkan dari y terbesar.
 * @param {{str: string, x: number, y: number}[]} items
 * @returns {string[]}
 */
export function groupTextItems(items, tolerance = 3) {
  const rows = [];
  const sorted = items.filter(i => i.str && i.str.trim()).sort((a, b) => b.y - a.y);
  for (const item of sorted) {
    const row = rows.find(r => Math.abs(r.y - item.y) <= tolerance);
    if (row) row.items.push(item);
    else rows.push({ y: item.y, items: [item] });
  }
  return rows.map(r => r.items
    .sort((a, b) => a.x - b.x)
    .map(i => i.str.trim())
    .join(' ')
    .replace(/\s+/g, ' '));
}

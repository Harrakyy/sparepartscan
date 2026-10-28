// Mengurai laporan Zahir "Penjualan - Rincian": satu PDF berisi banyak faktur, masing-masing
// ditandai "No. Reference". Fungsi murni, diuji dengan teks asli PDF (tests-js/fixtures).

const REPORT_TITLE = /Penjualan\s*-\s*Rincian/i;
const INVOICE_HEADER = /^Tanggal\s*:\s*(\d{2}\/\d{2}\/\d{4})\b.*Pelanggan\s*:/i;
const REFERENCE = /^No\.\s*Reference\s*:\s*(\d{4,32})\b/i;
const ITEMS_HEADER = /^Keterangan\s+Diterima\b/i;
const ITEMS_END = /^Salesman\s*:/i;
// Kepala dan kaki halaman (jam cetak, nomor halaman) bisa muncul di tengah daftar barang.
const PAGE_NOISE = /^(?:\d{1,2}:\d{2}\s+\d{1,2}\s+\p{L}+,?\s+\d{4}|Halaman\s*:\s*\d+)$/iu;
// Kode part di awal baris barang: HB-SHB-B07, P-MBR-LC-FR1, TB-ACCU-55B24L/ (bisa terpotong setelah "/").
const CODE = /^[A-Z0-9]{1,5}(?:-[A-Z0-9/]+)+$/i;
// "<Diterima> [<Dipesan>] <satuan> <harga>": jumlah yang keluar gudang = kolom Diterima.
const QTY_UNIT = /\s(\d+\.\d{2})\s+(?:\d+\.\d{2}\s+)?(?:Pcs|PC|Set|Unit|Box|Dus|Psg|Btl|Lbr|Roll|Mtr)\s+[\d.,]+/i;
// "(CUST 227 )", "(CUST-116 )"; nama panjang bisa membuat kodenya terpotong ke baris berikutnya.
const CUSTOMER_CODE = /\s*\(CUST[^)]*\)?\s*$/i;
const MAX_QTY = 10000;

export const isSalesReport = lines => lines.some(line => REPORT_TITLE.test(line));

function readItemLine(items, line) {
  const tokens = line.split(' ');
  const qty = line.match(QTY_UNIT);
  if (qty && CODE.test(tokens[0])) {
    items.push({ code: tokens[0].toUpperCase(), name: line.slice(tokens[0].length, qty.index).trim(), qty: Number(qty[1]) });
    return;
  }
  // Selain baris barang: lanjutan nama (atau kode) barang sebelumnya.
  const last = items.at(-1);
  if (!last) return;
  let rest = tokens;
  if (last.code.endsWith('/') && /^[A-Z0-9]+$/i.test(tokens[0])) {
    last.code += tokens[0].toUpperCase();
    rest = tokens.slice(1);
  }
  // "%" yatim dari kolom diskon yang terpotong ("17.5" lalu "%").
  const text = rest.filter(t => t !== '%').join(' ');
  if (text) last.name = `${last.name} ${text}`.trim();
}

// Kode yang sama dalam satu faktur (misalnya barang bonus diskon 100%) digabung jumlahnya.
function mergeItems(items) {
  const merged = new Map();
  for (const item of items) {
    const found = merged.get(item.code);
    if (found) found.qty += item.qty;
    else merged.set(item.code, { ...item });
  }
  return [...merged.values()];
}

function finish(invoice) {
  const items = mergeItems(invoice.items);
  const warnings = [];
  if (!invoice.customer) warnings.push('Nama penerima tidak terbaca');
  if (!items.length) warnings.push('Tidak ada barang yang terbaca');
  for (const item of items) {
    if (!Number.isInteger(item.qty) || item.qty < 1 || item.qty > MAX_QTY) {
      warnings.push(`Jumlah untuk ${item.code} tidak wajar (${item.qty})`);
    }
  }
  return { ...invoice, items, warnings };
}

/**
 * @param {string[]} rawLines baris teks semua halaman, dari atas ke bawah
 * @returns {{number: string, customer: string, date: string, items: {code: string, name: string, qty: number}[], warnings: string[]}[]}
 */
export function parseSalesReport(rawLines) {
  const lines = rawLines.map(l => String(l).replace(/\s+/g, ' ').trim()).filter(l => l && !PAGE_NOISE.test(l));
  const invoices = [];
  let current = null;
  let state = 'outside'; // customer → header → items → outside
  let date = '';
  let customerLines = [];

  for (const line of lines) {
    const header = line.match(INVOICE_HEADER);
    if (header) {
      [date, customerLines, current, state] = [header[1], [], null, 'customer'];
      continue;
    }
    const reference = line.match(REFERENCE);
    if (reference) {
      const customer = customerLines.join(' ').replace(CUSTOMER_CODE, '').trim();
      current = { number: reference[1], customer, date, items: [] };
      invoices.push(current);
      state = 'header';
      continue;
    }
    if (state === 'customer') customerLines.push(line);
    else if (current && ITEMS_HEADER.test(line)) state = 'items';
    else if (ITEMS_END.test(line)) state = 'outside';
    else if (current && state === 'items') readItemLine(current.items, line);
  }

  return invoices.map(finish);
}

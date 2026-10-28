import { apiGet, apiPost } from '../api.js';

// Laporan Zahir "Penjualan - Rincian": tiap No. Reference jadi satu sesi scan. Nomor yang sudah
// ada di app dilewati, jadi laporan yang sama (atau yang tumpang tindih) aman diupload ulang.
const STATUS = {
  new: ['Baru', 'badge-picked'],
  exists: ['Sudah ada, dilewati', 'badge-waiting'],
  check: ['Perlu dicek', 'badge-picking'],
};
const $ = id => document.getElementById(id);

let file = null;
let rows = [];
let onSaved = () => {};

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

const totalQty = invoice => invoice.items.reduce((sum, i) => sum + i.qty, 0);
const selected = () => rows.filter(r => r.checked);

function detailRow(row) {
  const tr = el('tr', 'report-detail');
  const td = el('td');
  td.colSpan = 5;
  if (row.invoice.warnings.length) td.append(el('p', 'msg msg-warn', row.invoice.warnings.join(' · ')));
  const list = el('table', 'grid');
  const body = el('tbody');
  body.append(...row.invoice.items.map(item => {
    const line = el('tr');
    line.append(el('td', 'code mono', item.code), el('td', '', item.name), el('td', 'num', String(item.qty)));
    return line;
  }));
  list.append(body);
  td.append(list);
  tr.append(td);
  return tr;
}

function invoiceRow(row) {
  const tr = el('tr', 'clickable');
  const box = el('input');
  box.type = 'checkbox';
  box.checked = row.checked;
  box.disabled = row.status === 'exists';
  box.setAttribute('aria-label', `Simpan faktur ${row.invoice.number}`);
  box.addEventListener('click', e => e.stopPropagation());
  box.addEventListener('change', () => { row.checked = box.checked; renderActions(); });
  const pick = el('td');
  pick.append(box);

  const number = el('td');
  number.append(el('span', 'code-chip', row.invoice.number));
  const [label, badgeClass] = STATUS[row.status];
  const status = el('td');
  status.append(el('span', `badge ${badgeClass}`, label));

  tr.append(pick, number, el('td', '', row.invoice.customer),
    el('td', 'num', `${row.invoice.items.length} kode · ${totalQty(row.invoice)} barang`), status);
  // Klik baris untuk melihat isi barangnya.
  tr.addEventListener('click', () => { row.open = !row.open; render(); });
  return row.open ? [tr, detailRow(row)] : [tr];
}

function renderActions() {
  const count = selected().length;
  $('report-save').disabled = count === 0;
  $('report-save').textContent = count ? `Simpan ${count} faktur ke Gudang` : 'Pilih faktur dulu';
  const selectable = rows.filter(r => r.status !== 'exists');
  $('report-all').checked = selectable.length > 0 && selectable.every(r => r.checked);
  $('report-all').disabled = selectable.length === 0;
}

function render() {
  $('report-list').replaceChildren(...rows.flatMap(invoiceRow));
  const counts = Object.fromEntries(Object.keys(STATUS).map(s => [s, rows.filter(r => r.status === s).length]));
  $('report-summary').textContent = `${rows.length} faktur terbaca dari ${file.name} · ${counts.new} baru`
    + (counts.exists ? ` · ${counts.exists} sudah ada` : '')
    + (counts.check ? ` · ${counts.check} perlu dicek` : '');
  renderActions();
}

/**
 * Tampilkan daftar faktur dari laporan. Faktur bermasalah (peringatan) tidak dicentang otomatis.
 * @param {File} reportFile
 * @param {{number: string, customer: string, date: string, items: object[], warnings: string[]}[]} invoices
 * @param {(message: string) => void} saved dipanggil setelah tersimpan, dengan pesan ringkasan
 */
export async function showReport(reportFile, invoices, saved) {
  file = reportFile;
  onSaved = saved;
  const numbers = invoices.map(i => i.number).join(',');
  const existing = new Set(numbers ? await apiGet(`/api/invoices/existing?numbers=${numbers}`) : []);
  rows = invoices.map(invoice => {
    const status = existing.has(invoice.number) ? 'exists' : invoice.warnings.length ? 'check' : 'new';
    return { invoice, status, checked: status === 'new', open: false };
  });
  $('report').hidden = false;
  render();
}

export function hideReport() {
  $('report').hidden = true;
  rows = [];
}

async function save() {
  const chosen = selected();
  const form = new FormData();
  form.append('file', file);
  form.append('payload', JSON.stringify({
    invoices: chosen.map(({ invoice }) => ({
      number: invoice.number,
      customer_name: invoice.customer,
      invoice_date: invoice.date || null,
      items: invoice.items.map(({ code, name, qty }) => ({ code, name, qty })),
    })),
  }));
  $('report-save').disabled = true;
  $('report-save').textContent = 'Menyimpan…';
  try {
    const { created, skipped } = await apiPost('/api/invoices/bulk', form);
    document.dispatchEvent(new Event('invoices-changed'));
    hideReport();
    onSaved(`${created.length} faktur masuk ke Gudang` + (skipped.length ? `, ${skipped.length} dilewati karena sudah ada` : '') + '.');
  } catch (err) {
    renderActions();
    throw err;
  }
}

$('report-all').addEventListener('change', () => {
  rows.filter(r => r.status !== 'exists').forEach(r => { r.checked = $('report-all').checked; });
  render();
});
$('report-save').addEventListener('click', () => save().catch(err => {
  $('status').className = 'msg msg-bad';
  $('status').textContent = err.message;
  $('status').hidden = false;
}));

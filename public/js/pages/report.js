import { apiGet } from '../api.js';
import { formatWib } from '../format.js';

const SEARCH_DEBOUNCE_MS = 300;
const $ = id => document.getElementById(id);

let seq = 0;
let timer = null;

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function itemRow(index, code, name, qty) {
  const tr = el('tr');
  const codeCell = el('td');
  codeCell.append(el('span', 'code-chip', code));
  tr.append(el('td', 'rownum', String(index + 1)), codeCell, el('td', '', name), el('td', 'num', String(qty)));
  return tr;
}

// Tanggal hari ini menurut WIB, format YYYY-MM-DD untuk input type="date".
function todayWib() {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date());
}

function setOpen(box, open) {
  box.classList.toggle('is-open', open);
  box.querySelector('.report-invoice-head').setAttribute('aria-expanded', String(open));
  box.querySelector('.report-invoice-body').hidden = !open;
}

// Satu faktur = baris yang bisa dibuka-tutup: klik barisnya atau tombol panah.
function invoiceBlock(inv) {
  const box = el('div', 'report-invoice');
  const head = el('button', 'report-invoice-head');
  head.type = 'button';
  const qty = inv.items.reduce((sum, i) => sum + i.qty, 0);
  const chevron = el('i', 'bi bi-chevron-down chevron');
  chevron.setAttribute('aria-hidden', 'true');
  head.append(
    el('span', 'code-chip', inv.number),
    el('strong', 'report-customer', inv.customer_name),
    el('span', 'muted', `Dicetak ${formatWib(inv.printed_at)}`),
    el('span', 'count-pill', `${inv.items.length} kode · ${qty} barang`),
    chevron,
  );
  head.addEventListener('click', () => setOpen(box, !box.classList.contains('is-open')));

  const body = el('div', 'report-invoice-body table-wrap');
  const table = el('table', 'grid');
  const thead = el('thead');
  thead.innerHTML = '<tr><th class="rownum">#</th><th>Kode part</th><th>Nama barang</th><th class="num">Jumlah</th></tr>';
  const tbody = el('tbody');
  tbody.append(...inv.items.map((i, n) => itemRow(n, i.part_code, i.part_name, i.qty)));
  table.append(thead, tbody);
  body.append(table);

  box.append(head, body);
  setOpen(box, false);
  return box;
}

function updateToggleAll() {
  const boxes = [...document.querySelectorAll('.report-invoice')];
  const allOpen = boxes.length > 0 && boxes.every(b => b.classList.contains('is-open'));
  $('toggle-all').hidden = boxes.length === 0;
  $('toggle-all').querySelector('span').textContent = allOpen ? 'Tutup semua' : 'Buka semua';
  $('toggle-all').querySelector('i').className = `bi ${allOpen ? 'bi-arrows-collapse' : 'bi-arrows-expand'}`;
}

async function load(event) {
  event?.preventDefault();
  if (!$('from').value || !$('to').value) return;
  if ($('from').value > $('to').value) {
    $('status').textContent = 'Tanggal awal tidak boleh setelah tanggal akhir';
    $('status').hidden = false;
    return;
  }
  const mySeq = ++seq;
  const params = new URLSearchParams({ from: $('from').value, to: $('to').value });
  if ($('q').value.trim()) params.set('q', $('q').value.trim());
  try {
    const { invoices } = await apiGet(`/api/reports/outgoing?${params}`);
    if (mySeq !== seq) return; // balasan lama datang setelah filter diganti lagi
    $('invoices').replaceChildren(...invoices.map(invoiceBlock));
    $('empty').hidden = invoices.length > 0;
    $('stat-invoices').textContent = invoices.length;
    $('stat-qty').textContent = invoices.reduce((sum, inv) => sum + inv.items.reduce((s, i) => s + i.qty, 0), 0);
    $('status').hidden = true;
    updateToggleAll();
  } catch (err) {
    if (mySeq !== seq) return;
    $('status').textContent = err.message;
    $('status').hidden = false;
  }
}

$('invoices').addEventListener('click', updateToggleAll);
$('toggle-all').addEventListener('click', () => {
  const boxes = [...document.querySelectorAll('.report-invoice')];
  const open = !boxes.every(b => b.classList.contains('is-open'));
  boxes.forEach(b => setOpen(b, open));
  updateToggleAll();
});

// Tanggal kalender (tengah malam waktu lokal) → YYYY-MM-DD.
const ymd = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const outgoingDays = new Set();

// Tandai tanggal yang ada barang keluarnya pada bulan yang tampil, termasuk hari bulan tetangga
// yang ikut terlihat. Diambil ulang setiap kalender dibuka supaya cetakan baru ikut hijau.
async function markOutgoingDays(fp) {
  const from = ymd(new Date(fp.currentYear, fp.currentMonth, -6));
  const to = ymd(new Date(fp.currentYear, fp.currentMonth + 1, 7));
  try {
    const days = await apiGet(`/api/reports/outgoing-days?from=${from}&to=${to}`);
    days.forEach(day => outgoingDays.add(day));
    fp.redraw();
  } catch {
    // Tanda hijau hanya bantuan; laporan tetap bisa dipakai tanpa tanda.
  }
}

function datePicker(input) {
  const refresh = (_dates, _str, fp) => markOutgoingDays(fp);
  return window.flatpickr(input, {
    locale: 'id',
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'd/m/Y',
    defaultDate: todayWib(),
    disableMobile: true,
    onDayCreate: (_dates, _str, _fp, day) => {
      if (!outgoingDays.has(ymd(day.dateObj))) return;
      day.classList.add('has-outgoing');
      day.title = 'Ada barang keluar';
    },
    onReady: refresh,
    onOpen: refresh,
    onMonthChange: refresh,
    onYearChange: refresh,
    // Laporan langsung diperbarui saat tanggal diganti, tanpa harus menekan "Tampilkan".
    onChange: () => load(),
  });
}

datePicker($('from'));
datePicker($('to'));
$('filter').addEventListener('submit', load);
$('q').addEventListener('input', () => {
  clearTimeout(timer);
  timer = setTimeout(load, SEARCH_DEBOUNCE_MS);
});
load();

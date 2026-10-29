import { apiGet, apiPost } from '../api.js';
import { formatWib } from '../format.js';
import { flashError } from '../flash.js';
import { setupScanInput } from '../scan-input.js';
import { setupDelete } from './session-admin.js';
import { suggestCode } from '../similar-code.js';
import { beepOk, buzzError } from '../sound.js';

const TOAST_MS = 3000;
const FLASH_MS = 900;
const RESULT_LABEL = { ok: 'Cocok', over_qty: 'Kelebihan', not_in_invoice: 'Tidak ada di faktur' };
const STAGE_LABEL = { pick: 'Scan pertama', qc: 'Scan QC' };
const $ = id => document.getElementById(id);
const invoiceId = $('session').dataset.invoiceId;

let invoice = null;
let stage = 'pick';
let toastTimer = null;
let queue = Promise.resolve();

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function iconCell(icon) {
  const td = el('td', 'icon');
  const i = el('i', `bi ${icon}`);
  i.setAttribute('aria-hidden', 'true');
  td.append(i);
  return td;
}

const column = () => (stage === 'qc' ? 'qc_qty' : 'picked_qty');
const allComplete = () => invoice.items.every(i => i[column()] >= i.qty);

// Tahap yang sedang aktif mengikuti status faktur; setelah scan pertama selesai,
// tombol "Mulai scan QC" memindahkan tampilan ke tahap QC.
function stageFromStatus(status) {
  return ['qc', 'qc_done'].includes(status) ? 'qc' : 'pick';
}

function isLocked() {
  return (stage === 'pick' && !['waiting', 'picking'].includes(invoice.status))
    || (stage === 'qc' && invoice.status === 'qc_done');
}

function showToast(text, kind = 'bad') {
  const t = $('toast');
  t.textContent = text;
  t.className = `toast toast-${kind}`;
  t.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { t.hidden = true; }, TOAST_MS);
}

function renderHeader() {
  const total = invoice.items.reduce((s, i) => s + i.qty, 0);
  const done = invoice.items.reduce((s, i) => s + Math.min(i[column()], i.qty), 0);
  let label = STAGE_LABEL[stage];
  if (stage === 'pick' && isLocked()) label = 'Scan pertama selesai';
  if (stage === 'qc' && isLocked()) label = 'Selesai QC';
  $('stage-label').textContent = label;
  $('progress-bar').style.width = `${total ? (done / total) * 100 : 0}%`;
  $('progress-text').textContent = `${done} dari ${total} barang`;
}

function renderItems(flashId) {
  $('items').replaceChildren(...invoice.items.map(item => {
    const n = item[column()];
    const complete = n >= item.qty;
    const tr = el('tr', complete ? 'row-ok' : n > 0 ? 'row-partial' : '');
    if (item.id === flashId) tr.classList.add('row-flash');
    tr.append(
      iconCell(complete ? 'bi-check-circle-fill' : n > 0 ? 'bi-circle-half' : 'bi-circle'),
      el('td', 'code mono', item.part_code),
      el('td', '', item.part_name),
      el('td', 'num count', `${n}/${item.qty}`),
    );
    return tr;
  }));
}

function renderWrong() {
  const codes = invoice.items.map(i => i.part_code);
  const counts = new Map();
  for (const e of invoice.scan_events) {
    if (e.stage === stage && e.result === 'not_in_invoice') counts.set(e.code, (counts.get(e.code) ?? 0) + 1);
  }
  $('wrong').replaceChildren(...[...counts].map(([code, n]) => {
    const tr = el('tr', 'row-bad');
    const suggestion = suggestCode(code, codes);
    const info = el('td', '', `Tidak ada di faktur${n > 1 ? ` (${n}×)` : ''}`);
    if (suggestion) info.append(el('span', 'muted', ` · Mungkin maksudnya ${suggestion}?`));
    tr.append(iconCell('bi-x-circle-fill'), el('td', 'code mono', code), info, el('td', 'num', ''));
    return tr;
  }));
}

function renderHistory() {
  $('history').replaceChildren(...invoice.scan_events.map(e => {
    const li = el('li', `history-${e.result}`);
    li.append(
      el('span', 'muted', formatWib(e.created_at)),
      el('span', '', STAGE_LABEL[e.stage]),
      el('span', 'mono', e.code),
      el('span', '', RESULT_LABEL[e.result] + (e.manual ? ' · diketik' : '')),
    );
    return li;
  }));
  $('history-count').textContent = invoice.scan_events.length;
  if (!invoice.scan_events.length) $('history').replaceChildren(el('li', 'muted', 'Belum ada scan.'));
}

// Cetak pertama = barang dianggap keluar (masuk laporan); cetak ulang hanya menambah riwayat.
function renderPrints() {
  const logs = invoice.print_logs;
  $('prints').replaceChildren(...logs.map((log, i) => {
    const li = el('li');
    li.append(
      el('span', 'muted', formatWib(log.created_at)),
      el('span', '', i === logs.length - 1 ? 'Cetak pertama · masuk laporan barang keluar' : 'Cetak ulang'),
    );
    return li;
  }));
  $('prints-count').textContent = logs.length;
  if (!logs.length) $('prints').replaceChildren(el('li', 'muted', 'Belum pernah dicetak.'));
}

// Hanya satu panel riwayat terbuka: membuka yang satu menutup yang lain.
const PANELS = { history: ['history-panel', 'toggle-history'], prints: ['prints-panel', 'toggle-prints'] };

function openPanel(which) {
  for (const [name, [panel, toggle]] of Object.entries(PANELS)) {
    $(panel).hidden = name !== which;
    $(toggle).setAttribute('aria-expanded', String(name === which));
  }
}

const togglePanel = which => openPanel($(PANELS[which][0]).hidden ? which : null);

function renderActions() {
  const locked = isLocked();
  $('complete').hidden = locked;
  $('complete').disabled = !allComplete();
  $('complete').querySelector('span').textContent = stage === 'qc' ? 'Selesai QC' : 'Selesai';
  $('start-qc').hidden = !(stage === 'pick' && invoice.status === 'picked');
  $('manual-form').hidden = locked;
  const printable = invoice.picked_at !== null;
  $('print').disabled = !printable;
  $('print').title = printable ? 'Cetak struk untuk pelanggan' : 'Selesaikan scan pertama dulu';
}

function render(flashId) {
  renderHeader();
  renderItems(flashId);
  renderWrong();
  renderHistory();
  renderPrints();
  renderActions();
  scanBox.focus();
  if (flashId) setTimeout(() => document.querySelector('.row-flash')?.classList.remove('row-flash'), FLASH_MS);
}

async function sendScan(code, manual) {
  if (!invoice) return showToast('Data faktur belum termuat. Muat ulang halaman.');
  if (isLocked()) return showToast('Sesi ini sudah selesai');
  try {
    const { scan, invoice: fresh } = await apiPost(`/api/invoices/${invoiceId}/scans`, { code, stage, manual });
    invoice = fresh;
    if (scan.result === 'ok') {
      beepOk();
      render(scan.item_id);
      document.querySelector('.row-flash')?.scrollIntoView({ block: 'nearest' });
    } else {
      buzzError();
      flashError();
      render();
      showToast(scan.message);
    }
  } catch (err) {
    buzzError();
    showToast(err.message);
  }
}

// Scan diproses berurutan supaya urutan dan hitungan tetap benar walau scanner cepat.
// Kegagalan satu scan tidak boleh menghentikan antrian untuk scan berikutnya.
const enqueueScan = (code, manual) => {
  queue = queue.then(() => sendScan(code, manual)).catch(err => {
    console.error(err);
    buzzError();
    showToast('Scan gagal diproses. Muat ulang halaman bila terus terjadi.');
  });
};

const scanBox = setupScanInput({
  form: $('manual-form'),
  input: $('manual-code'),
  ready: $('scan-ready'),
  canScan: () => Boolean(invoice) && !isLocked(),
  onScan: enqueueScan,
});

$('complete').addEventListener('click', async () => {
  $('complete').disabled = true;
  try {
    invoice = await apiPost(`/api/invoices/${invoiceId}/complete`, { stage });
    document.dispatchEvent(new Event('invoices-changed'));
    beepOk();
    showToast(stage === 'qc' ? 'Scan QC selesai' : 'Scan pertama selesai', 'ok');
    render();
  } catch (err) {
    buzzError();
    showToast(err.message);
    renderActions();
  }
});

$('toggle-history').addEventListener('click', () => togglePanel('history'));
$('close-history').addEventListener('click', () => openPanel(null));
$('toggle-prints').addEventListener('click', () => togglePanel('prints'));
$('close-prints').addEventListener('click', () => openPanel(null));

// Struk ikut barang ke pelanggan. Jumlah memakai scan pertama (wajib); scan QC opsional.
function renderReceipt() {
  const meta = [
    ['No faktur', invoice.number],
    ['Kepada', invoice.customer_name],
    ['Tgl faktur', invoice.invoice_date],
    ['Dicek', invoice.picked_at && formatWib(invoice.picked_at)],
    ['QC', invoice.qc_done_at && formatWib(invoice.qc_done_at)],
  ].filter(([, value]) => value);
  $('receipt-meta').replaceChildren(...meta.flatMap(([label, value]) => [el('dt', '', label), el('dd', '', value)]));

  $('receipt-items').replaceChildren(...invoice.items.map(item => {
    const short = item.picked_qty < item.qty;
    const text = el('span', 'receipt-item');
    text.append(el('span', 'receipt-code', item.part_code), ` ${item.part_name}`);
    const li = el('li');
    li.append(text, el('span', 'receipt-qty', short ? `${item.picked_qty}/${item.qty} KURANG` : String(item.qty)));
    return li;
  }));

  const total = invoice.items.reduce((s, i) => s + i.qty, 0);
  const verified = !['waiting', 'picking'].includes(invoice.status);
  $('receipt-total').textContent = `${invoice.items.length} jenis · ${total} barang`;
  $('receipt-status').textContent = verified ? 'SEMUA BARANG SESUAI FAKTUR' : 'BELUM SELESAI DICEK';
  $('receipt-printed').textContent = `Dicetak ${formatWib(new Date().toISOString())}`;
}

// Cetakan dicatat dulu ke server; bila gagal (koneksi putus), struk tidak dicetak supaya laporan
// tidak kehilangan barang yang sebenarnya sudah keluar.
$('print').addEventListener('click', async () => {
  if (!invoice) return showToast('Data faktur belum termuat. Muat ulang halaman.');
  $('print').disabled = true;
  try {
    invoice = await apiPost(`/api/invoices/${invoiceId}/prints`);
    document.dispatchEvent(new Event('invoices-changed'));
    render();
    renderReceipt();
    window.print();
  } catch (err) {
    buzzError();
    showToast(err.message);
    renderActions();
  }
});

$('start-qc').addEventListener('click', () => {
  stage = 'qc';
  render();
});

async function load() {
  try {
    invoice = await apiGet(`/api/invoices/${invoiceId}`);
    stage = stageFromStatus(invoice.status);
    render();
  } catch (err) {
    $('stage-label').textContent = err.message;
  }
}

setupDelete({ invoiceId, getInvoice: () => invoice });

load();

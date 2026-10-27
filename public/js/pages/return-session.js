import { apiDelete, apiGet, apiPatch, apiPost } from '../api.js';
import { flashError } from '../flash.js';
import { formatWib } from '../format.js';
import { setupScanInput } from '../scan-input.js';
import { beepOk, buzzError } from '../sound.js';

const TOAST_MS = 3000;
const FLASH_MS = 900;
const CONDITION_LABEL = { good: 'bagus', bad: 'jelek' };
const $ = id => document.getElementById(id);
const returnId = $('return').dataset.returnId;
const MODE_KEY = `return-mode-${returnId}`;
const base = `/api/returns/${returnId}`;

let ret = null;
let mode = 'good';
let toastTimer = null;
let queue = Promise.resolve();
let noteDirty = false; // catatan diketik tapi belum tersimpan: jangan ditimpa saat render

const isOpen = () => ret?.status === 'open';
const sum = key => ret.items.reduce((s, i) => s + i[key], 0);

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function showToast(text, kind = 'bad') {
  const t = $('toast');
  t.textContent = text;
  t.className = `toast toast-${kind}`;
  t.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { t.hidden = true; }, TOAST_MS);
}

// Mode BAGUS/JELEK menentukan kondisi setiap scan; bingkai layar berwarna supaya mode terlihat dari jauh.
function setMode(next) {
  mode = next;
  try { sessionStorage.setItem(MODE_KEY, mode); } catch { /* penyimpanan diblokir: mode tetap berlaku di halaman ini */ }
  renderMode();
}

function renderMode() {
  $('mode-good').setAttribute('aria-pressed', String(mode === 'good'));
  $('mode-bad').setAttribute('aria-pressed', String(mode === 'bad'));
  document.body.classList.toggle('mode-frame-good', isOpen() && mode === 'good');
  document.body.classList.toggle('mode-frame-bad', isOpen() && mode === 'bad');
}

function qtyCell(item, condition) {
  const td = el('td', 'num count');
  td.append(el('span', '', String(item[condition])));
  if (isOpen() && item[condition] > 0) {
    const minus = el('button', 'btn btn-icon btn-minus', '−1');
    minus.type = 'button';
    minus.title = `Kurangi 1 (${CONDITION_LABEL[condition]})`;
    minus.addEventListener('click', () => act(() => apiPost(`${base}/decrement`, { invoice_item_id: item.invoice_item_id, condition })));
    td.append(' ', minus);
  }
  return td;
}

function renderItems(flashId) {
  $('items').replaceChildren(...ret.items.map(item => {
    const tr = el('tr', item.good + item.bad > 0 ? 'row-ok' : '');
    if (item.invoice_item_id === flashId) tr.classList.add('row-flash');
    tr.append(
      el('td', 'code mono', item.part_code),
      el('td', '', item.part_name),
      el('td', 'num', String(item.shipped)),
      el('td', 'num muted', String(item.returned_before)),
      qtyCell(item, 'good'),
      qtyCell(item, 'bad'),
    );
    return tr;
  }));
}

function renderState() {
  const open = isOpen();
  const total = sum('good') + sum('bad');
  $('stage-label').textContent = open ? 'Sedang dicek' : 'Retur selesai';
  $('totals').textContent = `Bagus ${sum('good')} · Jelek ${sum('bad')}`;
  if (!noteDirty && document.activeElement !== $('note')) $('note').value = ret.note ?? '';
  $('note').disabled = !open;
  $('modes').hidden = !open;
  $('manual-form').hidden = !open;
  $('complete').hidden = !open;
  $('complete').disabled = total === 0;
  $('cancel').hidden = !(open && total === 0);
  $('done-panel').hidden = open;
  if (!open) {
    $('done-at').textContent = ret.done_at ? formatWib(ret.done_at) : '';
    $('zahir-state').textContent = ret.zahir_entered_at
      ? `Sudah diinput ke Zahir ${formatWib(ret.zahir_entered_at)}`
      : 'Belum diinput ke Zahir';
    $('zahir').textContent = ret.zahir_entered_at ? 'Batalkan tanda Zahir' : 'Tandai sudah diinput ke Zahir';
    $('zahir').className = ret.zahir_entered_at ? 'btn' : 'btn btn-primary';
  }
}

function render(flashId) {
  renderItems(flashId);
  renderState();
  renderMode();
  scanBox.focus();
  if (flashId) setTimeout(() => document.querySelector('.row-flash')?.classList.remove('row-flash'), FLASH_MS);
}

// Semua permintaan (scan, catatan, −1, Selesai, Zahir) diproses berurutan: hitungan tidak tertimpa
// balasan lama, dan catatan yang baru diketik tersimpan sebelum retur diselesaikan.
// Kegagalan satu permintaan tidak boleh menghentikan antrian.
function enqueue(task) {
  queue = queue.then(task).catch(() => {
    buzzError();
    showToast('Permintaan gagal diproses. Muat ulang halaman bila terus terjadi.');
  });
}

// Jalankan aksi yang membalas detail retur terbaru, lalu tampilkan ulang.
function act(request, okMessage) {
  enqueue(async () => {
    try {
      ret = await request();
      render();
      if (okMessage) showToast(okMessage, 'ok');
    } catch (err) {
      buzzError();
      showToast(err.message);
    }
  });
}

async function sendScan(code, manual) {
  if (!ret) return showToast('Data retur belum termuat. Muat ulang halaman.');
  if (!isOpen()) return showToast('Retur ini sudah selesai');
  try {
    const { scan, return: fresh } = await apiPost(`${base}/scans`, { code, condition: mode, manual });
    ret = fresh;
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

const scanBox = setupScanInput({
  form: $('manual-form'),
  input: $('manual-code'),
  ready: $('scan-ready'),
  canScan: isOpen,
  onScan: (code, manual) => enqueue(() => sendScan(code, manual)),
  captureFrom: [$('note')],
});

$('mode-good').addEventListener('click', () => setMode('good'));
$('mode-bad').addEventListener('click', () => setMode('bad'));
$('note').addEventListener('input', () => { noteDirty = true; });
// Isi catatan diambil sekarang, bukan saat antrian sampai: render scan sebelumnya tidak boleh mengubahnya.
$('note').addEventListener('change', () => {
  const note = $('note').value;
  act(async () => {
    const fresh = await apiPatch(base, { note });
    if ($('note').value === note) noteDirty = false;
    return fresh;
  }, 'Catatan tersimpan');
});
$('complete').addEventListener('click', () => act(() => apiPost(`${base}/complete`), 'Retur selesai'));
$('zahir').addEventListener('click', () => act(() => apiPost(`${base}/zahir`, { entered: !ret.zahir_entered_at })));

$('cancel').addEventListener('click', async () => {
  if (!window.confirm('Batalkan retur ini? Retur yang masih kosong akan dihapus.')) return;
  try {
    await apiDelete(base);
    window.location.href = '/retur';
  } catch (err) {
    buzzError();
    showToast(err.message);
  }
});

async function load() {
  try {
    mode = sessionStorage.getItem(MODE_KEY) === 'bad' ? 'bad' : 'good';
  } catch { /* penyimpanan diblokir: mulai dari BAGUS */ }
  try {
    ret = await apiGet(base);
    render();
  } catch (err) {
    $('stage-label').textContent = err.message;
  }
}

load();

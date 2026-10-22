import { apiGet, apiPost } from './api.js';
import { formatRupiah, parseWholeNumber } from './format.js';
import { normalizeBarcode, hasCheckDigit, createReadConfirmer } from './barcode.js';
import { createLiveScanner } from './live-scanner.js';

const MODE_KEY = 'scanMode';
const MODES = ['check', 'in', 'out'];
const MODE_LABEL = { in: 'masuk', out: 'keluar' };
const DUPLICATE_WINDOW_MS = 2000;
const TOAST_MS = 2500;
const SWIPE_PX = 50;
const SHEET_CLOSE_PX = 80;
const MAX_QTY = 100000;
const CAMERA_ERROR = 'Kamera tidak bisa dibuka. Izinkan akses kamera di pengaturan browser, atau ketuk tombol keyboard untuk mengetik barcode.';
const VIEWS = ['view-manual', 'view-part', 'view-register'];

const $ = id => document.getElementById(id);

let mode = loadMode();
let currentPart = null;
let lastScan = { code: '', at: 0 };
let camera = null;
let toastTimer = null;
// EAN/UPC sudah dijaga digit cek, cukup 2 bacaan sama. Format lain butuh 3.
const confirmChecked = createReadConfirmer({ required: 2 });
const confirmOther = createReadConfirmer({ required: 3 });

function loadMode() {
  try {
    const saved = localStorage.getItem(MODE_KEY);
    return MODES.includes(saved) ? saved : 'check';
  } catch {
    return 'check';
  }
}

function setMode(next) {
  mode = next;
  try { localStorage.setItem(MODE_KEY, next); } catch { /* penyimpanan browser tidak tersedia */ }
  document.body.dataset.mode = next;
  document.querySelectorAll('#modes [data-mode]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.mode === next)));
  if (currentPart && !$('sheet').hidden) renderPart(currentPart);
}

function shiftMode(step) {
  const i = MODES.indexOf(mode) + step;
  if (i >= 0 && i < MODES.length) setMode(MODES[i]);
}

function showStatus(text, kind) {
  const el = $('status');
  el.textContent = text;
  el.className = kind === 'ok' ? 'msg-ok' : 'msg-error';
  el.hidden = !text;
}

function showToast(text, kind) {
  const el = $('toast');
  el.textContent = text;
  el.className = `toast ${kind === 'out' ? 'out' : ''}`;
  el.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.hidden = true; }, TOAST_MS);
}

// --- Lembar bawah (bottom sheet) ---

function openSheet(view) {
  VIEWS.forEach(id => { $(id).hidden = id !== view; });
  $('sheet').style.transform = '';
  $('sheet').hidden = false;
  $('backdrop').hidden = false;
  camera?.pause();
}

function closeSheet() {
  currentPart = null;
  $('sheet').hidden = true;
  $('backdrop').hidden = true;
  showStatus('');
  $('move-qty').value = '1';
  $('move-note').value = '';
  camera?.resume();
}

function renderPart(part) {
  currentPart = part;
  openSheet('view-part');
  $('r-part-number').textContent = part.part_number;
  $('r-description').textContent = part.description;
  $('r-barcode').textContent = part.barcode;
  $('r-price-retail').textContent = formatRupiah(part.price_retail);
  $('r-price-member').textContent = formatRupiah(part.price_member);
  $('r-price-wholesale').textContent = formatRupiah(part.price_wholesale);
  $('r-stock').textContent = part.stock;

  const recording = mode !== 'check';
  $('quick-actions').hidden = recording;
  $('move-form').hidden = !recording;
  const submit = $('move-submit');
  submit.textContent = `Simpan ${MODE_LABEL[mode] ?? ''}`;
  submit.className = `btn btn-block ${mode === 'in' ? 'btn-in' : 'btn-out'}`;
}

function showRegister(barcode) {
  currentPart = null;
  openSheet('view-register');
  $('register-form').reset();
  $('reg-barcode').value = barcode;
}

async function lookup(rawBarcode) {
  const barcode = rawBarcode.trim();
  if (!barcode) return;
  currentPart = null;
  openSheet(null);
  showStatus('Mencari part…', 'ok');
  try {
    const part = await apiGet(`/api/parts/by-barcode/${encodeURIComponent(barcode)}`);
    showStatus('');
    renderPart(part);
  } catch (err) {
    showStatus('');
    if (err.status === 404) showRegister(barcode);
    else showStatus(err.message);
  }
}

async function withBusy(button, fn) {
  button.disabled = true;
  try { await fn(); } finally { button.disabled = false; }
}

function stepQty(delta) {
  const now = parseWholeNumber($('move-qty').value);
  const next = (Number.isNaN(now) ? 1 : now) + delta;
  $('move-qty').value = String(Math.min(MAX_QTY, Math.max(1, next)));
}

async function submitMovement(event) {
  event.preventDefault();
  if (!currentPart) return;
  const qty = parseWholeNumber($('move-qty').value);
  const type = mode;
  await withBusy($('move-submit'), async () => {
    try {
      const { part } = await apiPost('/api/movements', { part_id: currentPart.id, type, qty, note: $('move-note').value });
      // lastScan tetap, jadi barcode yang sama diabaikan 2 detik setelah kamera jalan lagi.
      closeSheet();
      showToast(`${type === 'in' ? 'Masuk +' : 'Keluar −'}${qty} · stok ${part.stock}`, type);
      $('last-stock').textContent = part.stock;
      $('last-stock').hidden = false;
    } catch (err) {
      showStatus(err.message);
    }
  });
}

async function submitRegister(event) {
  event.preventDefault();
  await withBusy($('reg-submit'), async () => {
    try {
      const part = await apiPost('/api/parts', {
        barcode: $('reg-barcode').value,
        part_number: $('reg-part-number').value,
        description: $('reg-description').value,
        price_retail: parseWholeNumber($('reg-price-retail').value),
        price_member: parseWholeNumber($('reg-price-member').value),
        price_wholesale: parseWholeNumber($('reg-price-wholesale').value),
      });
      renderPart(part);
      showStatus('Part terdaftar.', 'ok');
    } catch (err) {
      showStatus(err.message);
    }
  });
}

// --- Kamera ---

function onScan(text, formatName) {
  const code = normalizeBarcode(text, formatName);
  if (!code) return;
  const confirm = hasCheckDigit(formatName) ? confirmChecked : confirmOther;
  if (!confirm(code)) return;
  const now = Date.now();
  if (code === lastScan.code && now - lastScan.at < DUPLICATE_WINDOW_MS) return;
  lastScan = { code, at: now };
  navigator.vibrate?.(80);
  beep();
  lookup(code);
}

// Bunyi "beep" saat barcode diterima. Browser baru mengizinkan suara setelah layar disentuh.
let audioCtx = null;
document.addEventListener('pointerdown', () => {
  try {
    audioCtx ??= new (window.AudioContext || window.webkitAudioContext)();
    if (audioCtx.state === 'suspended') audioCtx.resume();
  } catch { /* suara tidak tersedia */ }
}, { once: true });

function beep() {
  if (!audioCtx) return;
  try {
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.frequency.value = 880;
    gain.gain.value = 0.08;
    osc.connect(gain).connect(audioCtx.destination);
    osc.start();
    osc.stop(audioCtx.currentTime + 0.12);
  } catch { /* suara tidak tersedia */ }
}

function showCameraError() {
  $('frame').hidden = true;
  $('scan-hint').hidden = true;
  $('camera-msg').textContent = CAMERA_ERROR;
  $('camera-msg').hidden = false;
}

async function startScanner() {
  camera = createLiveScanner({
    onScan,
    onHint: text => { $('scan-hint').textContent = text; },
    onDetect: found => $('frame').classList.toggle('detected', found),
    ui: { video: $('video'), torch: $('torch'), zoomChips: $('zoom-chips'), switchCamera: $('switch-camera') },
  });
  try {
    await camera.start();
  } catch (err) {
    console.error(err);
    camera = null;
    showCameraError();
  }
}

// --- Gestur ---

// Geser kiri/kanan di gambar kamera untuk ganti mode, seperti FOTO/VIDEO di aplikasi kamera.
let swipe = null;
$('camera').addEventListener('touchstart', e => {
  swipe = e.touches.length === 1 && !e.target.closest('button, a')
    ? { x: e.touches[0].clientX, y: e.touches[0].clientY }
    : null;
}, { passive: true });
$('camera').addEventListener('touchend', e => {
  if (!swipe) return;
  const dx = e.changedTouches[0].clientX - swipe.x;
  const dy = e.changedTouches[0].clientY - swipe.y;
  swipe = null;
  if (Math.abs(dx) > SWIPE_PX && Math.abs(dx) > Math.abs(dy)) shiftMode(dx < 0 ? 1 : -1);
});

// Tarik lembar ke bawah untuk menutup.
let drag = null;
$('sheet-grab').addEventListener('touchstart', e => { drag = e.touches[0].clientY; }, { passive: true });
$('sheet-grab').addEventListener('touchmove', e => {
  if (drag === null) return;
  $('sheet').style.transform = `translateY(${Math.max(0, e.touches[0].clientY - drag)}px)`;
}, { passive: true });
$('sheet-grab').addEventListener('touchend', e => {
  if (drag === null) return;
  const dy = e.changedTouches[0].clientY - drag;
  drag = null;
  if (dy > SHEET_CLOSE_PX) closeSheet();
  else $('sheet').style.transform = '';
});

// --- Tombol ---

document.querySelectorAll('#modes [data-mode]').forEach(b => b.addEventListener('click', () => setMode(b.dataset.mode)));
document.querySelectorAll('[data-quick]').forEach(b => b.addEventListener('click', () => setMode(b.dataset.quick)));
$('open-manual').addEventListener('click', () => {
  openSheet('view-manual');
  $('manual-barcode').value = '';
  $('manual-barcode').focus();
});
$('manual-form').addEventListener('submit', event => {
  event.preventDefault();
  lookup($('manual-barcode').value);
});
$('qty-minus').addEventListener('click', () => stepQty(-1));
$('qty-plus').addEventListener('click', () => stepQty(1));
$('move-form').addEventListener('submit', submitMovement);
$('register-form').addEventListener('submit', submitRegister);
$('sheet-close').addEventListener('click', () => {
  lastScan = { code: '', at: 0 };
  closeSheet();
});
$('backdrop').addEventListener('click', closeSheet);
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && !$('sheet').hidden) closeSheet();
});

setMode(mode);
startScanner();

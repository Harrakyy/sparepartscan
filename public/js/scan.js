import { apiGet, apiPost } from './api.js';
import { formatRupiah, parseWholeNumber } from './format.js';
import { normalizeBarcode, hasCheckDigit, createReadConfirmer } from './barcode.js';
import { createLiveScanner } from './live-scanner.js';

const MODE_KEY = 'scanMode';
const MODES = ['check', 'in', 'out'];
const DUPLICATE_WINDOW_MS = 2000;
const CAMERA_ERROR = 'Kamera tidak bisa dibuka. Izinkan akses kamera di pengaturan browser, atau ketik barcode di bawah.';

const $ = id => document.getElementById(id);

let mode = loadMode();
let currentPart = null;
let lastScan = { code: '', at: 0 };
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
  document.querySelectorAll('[data-mode]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.mode === next)));
  if (currentPart) renderPart(currentPart);
}

function showStatus(text, kind) {
  const el = $('status');
  el.textContent = text;
  el.className = kind === 'ok' ? 'msg-ok' : 'msg-error';
  el.hidden = !text;
}

function renderPart(part) {
  currentPart = part;
  $('register').hidden = true;
  $('result').hidden = false;
  $('r-part-number').textContent = part.part_number;
  $('r-description').textContent = part.description;
  $('r-barcode').textContent = part.barcode;
  $('r-price-retail').textContent = formatRupiah(part.price_retail);
  $('r-price-member').textContent = formatRupiah(part.price_member);
  $('r-price-wholesale').textContent = formatRupiah(part.price_wholesale);
  $('r-stock').textContent = part.stock;

  const form = $('move-form');
  form.hidden = mode === 'check';
  const submit = $('move-submit');
  submit.textContent = mode === 'in' ? 'Simpan Masuk' : 'Simpan Keluar';
  submit.className = `btn btn-block ${mode === 'in' ? 'btn-in' : 'btn-out'}`;
}

function showRegister(barcode) {
  currentPart = null;
  $('result').hidden = true;
  $('register').hidden = false;
  $('register-form').reset();
  $('reg-barcode').value = barcode;
  $('reg-part-number').focus();
}

function resetView() {
  currentPart = null;
  $('result').hidden = true;
  $('register').hidden = true;
  $('scan-again').hidden = true;
  showStatus('');
  $('move-qty').value = '1';
  $('move-note').value = '';
}

async function lookup(rawBarcode) {
  const barcode = rawBarcode.trim();
  if (!barcode) return;
  showStatus('');
  currentPart = null;
  $('result').hidden = true;
  $('register').hidden = true;
  $('scan-again').hidden = false;
  try {
    renderPart(await apiGet(`/api/parts/by-barcode/${encodeURIComponent(barcode)}`));
  } catch (err) {
    if (err.status === 404) showRegister(barcode);
    else showStatus(err.message);
  }
}

async function withBusy(button, fn) {
  button.disabled = true;
  try { await fn(); } finally { button.disabled = false; }
}

async function submitMovement(event) {
  event.preventDefault();
  if (!currentPart) return;
  await withBusy($('move-submit'), async () => {
    try {
      const { part } = await apiPost('/api/movements', {
        part_id: currentPart.id,
        type: mode,
        qty: parseWholeNumber($('move-qty').value),
        note: $('move-note').value,
      });
      renderPart(part);
      $('move-qty').value = '1';
      $('move-note').value = '';
      showStatus(`Tersimpan. Stok sekarang: ${part.stock}`, 'ok');
      // Kembali ke mode scan; lastScan tetap, jadi barcode yang sama diabaikan 2 detik.
      $('move-form').hidden = true;
      resumeScanner();
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
  pauseScanner();
  lookup(code);
}

let camera = null;

function pauseScanner() {
  camera?.pause();
}

function resumeScanner() {
  camera?.resume();
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
  $('viewfinder').hidden = true;
  $('camera-controls').hidden = true;
  $('zoom-wrap').hidden = true;
  $('scan-hint').hidden = true;
  $('camera-msg').textContent = CAMERA_ERROR;
  $('camera-msg').hidden = false;
}

async function startScanner() {
  camera = createLiveScanner({
    onScan,
    onHint: text => { $('scan-hint').textContent = text; },
    ui: {
      video: $('video'),
      overlay: $('overlay'),
      torch: $('torch'),
      zoom: $('zoom'),
      zoomWrap: $('zoom-wrap'),
      switchCamera: $('switch-camera'),
    },
  });
  try {
    await camera.start();
  } catch (err) {
    console.error(err);
    camera = null;
    showCameraError();
  }
}

document.querySelectorAll('[data-mode]').forEach(b => b.addEventListener('click', () => setMode(b.dataset.mode)));
$('manual-form').addEventListener('submit', event => {
  event.preventDefault();
  pauseScanner();
  lookup($('manual-barcode').value);
});
$('move-form').addEventListener('submit', submitMovement);
$('register-form').addEventListener('submit', submitRegister);
$('scan-again').addEventListener('click', () => {
  resetView();
  $('manual-barcode').value = '';
  lastScan = { code: '', at: 0 };
  resumeScanner();
});

setMode(mode);
startScanner();

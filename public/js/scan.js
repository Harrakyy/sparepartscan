import { apiGet, apiPost } from './api.js';
import { formatRupiah, parseWholeNumber } from './format.js';
import { normalizeBarcode, hasCheckDigit, createReadConfirmer } from './barcode.js';

const MODE_KEY = 'scanMode';
const MODES = ['check', 'in', 'out'];
const DUPLICATE_WINDOW_MS = 2000;
const CAMERA_ERROR = 'Kamera tidak bisa dibuka. Izinkan akses kamera di pengaturan browser, atau ketik barcode di bawah.';

const $ = id => document.getElementById(id);

let mode = loadMode();
let currentPart = null;
let scanner = null;
let scannerPaused = false;
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

function onScan(text, result) {
  const formatName = result?.result?.format?.formatName;
  const code = normalizeBarcode(text, formatName);
  if (!code) return;
  const confirm = hasCheckDigit(formatName) ? confirmChecked : confirmOther;
  if (!confirm(code)) return;
  const now = Date.now();
  if (code === lastScan.code && now - lastScan.at < DUPLICATE_WINDOW_MS) return;
  lastScan = { code, at: now };
  navigator.vibrate?.(80);
  pauseScanner();
  lookup(code);
}

function pauseScanner() {
  if (!scanner || scannerPaused) return;
  try { scanner.pause(true); scannerPaused = true; } catch { /* kamera belum siap */ }
}

function resumeScanner() {
  if (!scanner || !scannerPaused) return;
  try { scanner.resume(); scannerPaused = false; } catch { /* kamera sudah berhenti */ }
}

// Flash hanya tampil kalau kamera HP mendukungnya (umumnya Chrome Android; Safari iPhone belum).
function setupTorch() {
  let torch;
  try {
    torch = scanner.getRunningTrackCameraCapabilities().torchFeature();
  } catch {
    return;
  }
  if (!torch.isSupported()) return;
  const button = $('torch');
  const render = on => {
    button.setAttribute('aria-pressed', String(on));
    button.textContent = on ? 'Matikan flash' : 'Nyalakan flash';
  };
  render(Boolean(torch.value()));
  button.hidden = false;
  button.addEventListener('click', async () => {
    const next = !torch.value();
    try {
      await torch.apply(next);
      render(next);
    } catch (err) {
      console.error(err);
      showStatus('Flash tidak bisa dinyalakan di HP ini.');
    }
  });
}

async function startScanner() {
  const lib = window.__Html5QrcodeLibrary__;
  if (!lib) {
    $('camera-msg').textContent = CAMERA_ERROR;
    $('camera-msg').hidden = false;
    return;
  }
  const F = lib.Html5QrcodeSupportedFormats;
  scanner = new lib.Html5Qrcode('reader', {
    verbose: false,
    formatsToSupport: [F.EAN_13, F.EAN_8, F.UPC_A, F.UPC_E, F.CODE_128, F.CODE_39, F.QR_CODE],
    experimentalFeatures: { useBarCodeDetectorIfSupported: true },
  });
  try {
    await scanner.start({ facingMode: 'environment' }, {
      fps: 15,
      // Area baca lebar dan pendek, sesuai bentuk barcode 1D.
      qrbox: (w, h) => ({ width: Math.max(50, Math.floor(w * 0.85)), height: Math.max(50, Math.floor(h * 0.4)) }),
      videoConstraints: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
    }, onScan, () => {});
    setupTorch();
  } catch (err) {
    console.error(err);
    scanner = null;
    $('reader').hidden = true;
    $('camera-msg').textContent = CAMERA_ERROR;
    $('camera-msg').hidden = false;
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

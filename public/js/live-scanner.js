// Scanner kamera memakai BarcodeDetector bawaan browser. Browser tanpa BarcodeDetector
// (Safari iPhone, Firefox) memakai polyfill barcode-detector (zxing-cpp WASM).
import { pickBackCamera, nextCameraId } from './camera.js';
import { toFormatName, isSmallInFrame, isDarkFrame, clampZoom } from './scan-hints.js';

const FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'qr_code'];
const WASM_URL = '/vendor/zxing_reader.wasm';
const DETECT_INTERVAL_MS = 80;
const DARK_CHECK_MS = 1000;
const DEFAULT_ZOOM = 2;
const FEATURE_RETRY_MS = [0, 400, 1200]; // flash/zoom kadang baru terbaca sesaat setelah kamera jalan
const REFOCUS_MS = 1500;
const CAMERA_KEY = 'cameraId';

const HINT = {
  idle: 'Arahkan kamera ke barcode',
  closer: 'Barcode terdeteksi, dekatkan sedikit',
  dark: 'Terlalu gelap, nyalakan flash atau tambah cahaya',
};

const wait = ms => new Promise(resolve => setTimeout(resolve, ms));

function load(key) {
  try { return localStorage.getItem(key); } catch { return null; }
}

function save(key, value) {
  try { localStorage.setItem(key, value); } catch { /* penyimpanan browser tidak tersedia */ }
}

async function createDetector() {
  window.BarcodeDetectionAPI?.prepareZXingModule({
    overrides: { locateFile: (path, prefix) => (path.endsWith('.wasm') ? WASM_URL : prefix + path) },
  });
  if (!('BarcodeDetector' in window)) throw new Error('BarcodeDetector tidak tersedia');
  let formats = FORMATS;
  try {
    const supported = await window.BarcodeDetector.getSupportedFormats();
    const usable = FORMATS.filter(f => supported.includes(f));
    if (usable.length) formats = usable;
  } catch { /* pakai daftar bawaan */ }
  return new window.BarcodeDetector({ formats });
}

function videoConstraints(cameraId) {
  const base = { width: { ideal: 1280 }, height: { ideal: 720 }, advanced: [{ focusMode: 'continuous' }] };
  return cameraId ? { ...base, deviceId: { exact: cameraId } } : { ...base, facingMode: { ideal: 'environment' } };
}

// ui: { video, overlay, torch, zoom, zoomWrap, switchCamera }
export function createLiveScanner({ ui, onScan, onHint }) {
  let detector = null;
  let stream = null;
  let track = null;
  let caps = {};
  let cameras = [];
  let cameraId = null;
  let paused = false;
  let running = false;
  let torchOn = false;
  let lastDarkCheck = 0;
  let hint = '';
  const probe = document.createElement('canvas');
  probe.width = 32;
  probe.height = 24;

  const setHint = text => {
    if (text === hint) return;
    hint = text;
    onHint(text);
  };

  const apply = async constraint => {
    if (!track) return false;
    try {
      await track.applyConstraints({ advanced: [constraint] });
      return true;
    } catch (err) {
      console.error('Gagal mengatur kamera', constraint, err);
      return false;
    }
  };

  function drawBox(box) {
    const ctx = ui.overlay.getContext('2d');
    ui.overlay.width = ui.video.videoWidth || ui.overlay.width;
    ui.overlay.height = ui.video.videoHeight || ui.overlay.height;
    ctx.clearRect(0, 0, ui.overlay.width, ui.overlay.height);
    if (!box) return;
    ctx.lineWidth = Math.max(4, ui.overlay.width / 200);
    ctx.strokeStyle = '#3fb59b';
    ctx.strokeRect(box.x, box.y, box.width, box.height);
  }

  function checkDark(now) {
    if (now - lastDarkCheck < DARK_CHECK_MS) return;
    lastDarkCheck = now;
    const ctx = probe.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(ui.video, 0, 0, probe.width, probe.height);
    setHint(isDarkFrame(ctx.getImageData(0, 0, probe.width, probe.height).data) ? HINT.dark : HINT.idle);
  }

  // Baca gambar kamera setiap 80 ms; jadwal berikutnya dibuat setelah pembacaan selesai.
  async function tick() {
    if (!running) return;
    const now = performance.now();
    if (!paused && ui.video.readyState >= 2) {
      try {
        const [code] = await detector.detect(ui.video);
        if (code && !paused) {
          drawBox(code.boundingBox);
          setHint(isSmallInFrame(code.boundingBox, ui.video.videoWidth) ? HINT.closer : HINT.idle);
          onScan(code.rawValue, toFormatName(code.format));
        } else if (!paused) {
          drawBox(null);
          checkDark(now);
        }
      } catch (err) {
        console.error('Gagal membaca gambar kamera', err);
      }
    }
    setTimeout(tick, DETECT_INTERVAL_MS);
  }

  function renderTorch() {
    ui.torch.hidden = !('torch' in caps);
    ui.torch.setAttribute('aria-pressed', String(torchOn));
    ui.torch.textContent = torchOn ? 'Flash: nyala' : 'Flash: mati';
  }

  async function setupFeatures() {
    for (const delay of FEATURE_RETRY_MS) {
      await wait(delay);
      try { caps = track.getCapabilities?.() || {}; } catch { caps = {}; }
      if ('torch' in caps || caps.zoom) break;
    }
    torchOn = false;
    renderTorch();
    ui.zoomWrap.hidden = !(caps.zoom && caps.zoom.max > 1);
    if (!ui.zoomWrap.hidden) {
      ui.zoom.min = String(caps.zoom.min ?? 1);
      ui.zoom.max = String(caps.zoom.max);
      ui.zoom.step = String(caps.zoom.step || 0.1);
      // Zoom 2x supaya HP bisa dipegang lebih jauh, di luar jarak minimum fokus.
      const z = clampZoom(DEFAULT_ZOOM, caps.zoom.min ?? 1, caps.zoom.max);
      if (await apply({ zoom: z })) ui.zoom.value = String(z);
    }
  }

  function stopStream() {
    stream?.getTracks().forEach(t => t.stop());
    stream = null;
    track = null;
  }

  async function openCamera(id) {
    stopStream();
    stream = await navigator.mediaDevices.getUserMedia({ video: videoConstraints(id), audio: false });
    track = stream.getVideoTracks()[0] || null;
    cameraId = track?.getSettings?.().deviceId || id;
    ui.video.srcObject = stream;
    await ui.video.play();
    if (id) save(CAMERA_KEY, id);
    ui.switchCamera.hidden = cameras.length < 2 || nextCameraId(cameras, cameraId) === cameraId;
    await setupFeatures();
  }

  async function listCameras() {
    const devices = await navigator.mediaDevices.enumerateDevices();
    return devices.filter(d => d.kind === 'videoinput').map(d => ({ id: d.deviceId, label: d.label }));
  }

  ui.torch.addEventListener('click', async () => {
    if (await apply({ torch: !torchOn })) torchOn = !torchOn;
    renderTorch();
  });

  ui.zoom.addEventListener('input', () => apply({ zoom: Number(ui.zoom.value) }));

  // Cubit dua jari di gambar kamera untuk zoom.
  let pinch = null;
  const distance = e => Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
  ui.video.addEventListener('touchstart', e => {
    if (e.touches.length === 2 && caps.zoom) pinch = { start: distance(e), zoom: Number(ui.zoom.value) };
  }, { passive: true });
  ui.video.addEventListener('touchmove', e => {
    if (!pinch || e.touches.length !== 2) return;
    e.preventDefault();
    const z = clampZoom(pinch.zoom * (distance(e) / pinch.start), Number(ui.zoom.min), Number(ui.zoom.max));
    ui.zoom.value = String(z);
    apply({ zoom: z });
  }, { passive: false });
  ui.video.addEventListener('touchend', () => { pinch = null; });

  // Ketuk gambar kamera untuk fokus ulang (bila HP mendukung).
  ui.video.addEventListener('click', async () => {
    if (!caps.focusMode?.includes('single-shot')) return;
    await apply({ focusMode: 'single-shot' });
    await wait(REFOCUS_MS);
    if (caps.focusMode.includes('continuous')) await apply({ focusMode: 'continuous' });
  });

  ui.switchCamera.addEventListener('click', async () => {
    const next = nextCameraId(cameras, cameraId);
    if (!next || next === cameraId) return;
    ui.switchCamera.disabled = true;
    try {
      await openCamera(next);
    } catch (err) {
      console.error(err);
    } finally {
      ui.switchCamera.disabled = false;
    }
  });

  return {
    async start() {
      if (!navigator.mediaDevices?.getUserMedia) throw new Error('Kamera tidak didukung browser ini');
      detector = await createDetector();
      // Nama kamera baru terbaca setelah izin diberikan, jadi buka kamera bawaan dulu.
      const saved = load(CAMERA_KEY);
      try {
        await openCamera(saved);
      } catch {
        await openCamera(null);
      }
      try { cameras = await listCameras(); } catch { cameras = []; }
      const preferred = pickBackCamera(cameras, saved);
      if (preferred && preferred !== cameraId) {
        try {
          await openCamera(preferred);
        } catch (err) {
          console.error('Kamera pilihan gagal, tetap pakai kamera sekarang', err);
          await openCamera(null);
        }
      } else {
        ui.switchCamera.hidden = cameras.length < 2 || nextCameraId(cameras, cameraId) === cameraId;
      }
      running = true;
      setHint(HINT.idle);
      tick();
    },
    pause() {
      paused = true;
      drawBox(null);
    },
    resume() {
      paused = false;
    },
  };
}

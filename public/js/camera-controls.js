import { pickBackCamera, nextCameraId } from './camera.js';

const CAMERA_KEY = 'cameraId';
const ZOOM_TARGET = 2;
const FEATURE_RETRY_MS = [0, 400, 1200]; // kemampuan flash/zoom kadang baru muncul sesaat setelah kamera jalan
const REFOCUS_MS = 1500;

function load(key) {
  try { return localStorage.getItem(key); } catch { return null; }
}

function save(key, value) {
  try { localStorage.setItem(key, value); } catch { /* penyimpanan browser tidak tersedia */ }
}

const wait = ms => new Promise(resolve => setTimeout(resolve, ms));

function scanConfig(cameraId) {
  const video = { width: { ideal: 1280 }, height: { ideal: 720 }, advanced: [{ focusMode: 'continuous' }] };
  return {
    fps: 15,
    // Area baca lebar dan pendek, sesuai bentuk barcode 1D.
    qrbox: (w, h) => ({ width: Math.max(50, Math.floor(w * 0.85)), height: Math.max(50, Math.floor(h * 0.4)) }),
    videoConstraints: cameraId ? { ...video, deviceId: { exact: cameraId } } : { ...video, facingMode: 'environment' },
  };
}

// Mengurus kamera: memilih lensa utama, fokus, zoom, flash, dan ganti kamera.
// `ui` berisi tombol torch, zoom, switchCamera, dan elemen reader.
export function createCameraScanner({ lib, readerId, formats, onScan, ui }) {
  const scanner = new lib.Html5Qrcode(readerId, {
    verbose: false,
    formatsToSupport: formats,
    experimentalFeatures: { useBarCodeDetectorIfSupported: true },
  });
  let cameras = [];
  let cameraId = null;
  let paused = false;
  let caps = {};
  let torchOn = false;
  let zoomOn = false;

  const apply = async constraint => {
    try {
      await scanner.applyVideoConstraints({ advanced: [constraint] });
      return true;
    } catch (err) {
      console.error('Gagal mengatur kamera', constraint, err);
      return false;
    }
  };

  function renderTorch() {
    ui.torch.hidden = !('torch' in caps);
    ui.torch.setAttribute('aria-pressed', String(torchOn));
    ui.torch.textContent = torchOn ? 'Flash: nyala' : 'Flash: mati';
  }

  function renderZoom() {
    ui.zoom.hidden = !(caps.zoom && caps.zoom.max > 1);
    ui.zoom.setAttribute('aria-pressed', String(zoomOn));
    ui.zoom.textContent = zoomOn ? 'Zoom: 2x' : 'Zoom: 1x';
  }

  function zoomValue(on) {
    return on ? Math.min(ZOOM_TARGET, caps.zoom.max) : Math.max(1, caps.zoom.min);
  }

  async function detectFeatures() {
    for (const delay of FEATURE_RETRY_MS) {
      await wait(delay);
      try { caps = scanner.getRunningTrackCapabilities() || {}; } catch { caps = {}; }
      if ('torch' in caps || caps.zoom) break;
    }
    torchOn = false;
    if (caps.focusMode?.includes('continuous')) await apply({ focusMode: 'continuous' });
    // Zoom 2x supaya HP bisa dipegang lebih jauh, di luar jarak minimum fokus.
    zoomOn = Boolean(caps.zoom && caps.zoom.max > 1) && await apply({ zoom: zoomValue(true) });
    renderTorch();
    renderZoom();
  }

  async function startCamera(id) {
    await scanner.start(id ?? { facingMode: 'environment' }, scanConfig(id), onScan, () => {});
    paused = false;
    cameraId = id;
    if (id) save(CAMERA_KEY, id);
    ui.switchCamera.hidden = nextCameraId(cameras, id) === id || cameras.length < 2;
    await detectFeatures();
  }

  ui.torch.addEventListener('click', async () => {
    if (await apply({ torch: !torchOn })) torchOn = !torchOn;
    renderTorch();
  });

  ui.zoom.addEventListener('click', async () => {
    if (await apply({ zoom: zoomValue(!zoomOn) })) zoomOn = !zoomOn;
    renderZoom();
  });

  ui.switchCamera.addEventListener('click', async () => {
    const next = nextCameraId(cameras, cameraId);
    if (!next || next === cameraId) return;
    ui.switchCamera.disabled = true;
    try {
      await scanner.stop();
      await startCamera(next);
    } catch (err) {
      console.error(err);
    } finally {
      ui.switchCamera.disabled = false;
    }
  });

  // Ketuk gambar kamera untuk fokus ulang (bila HP mendukung).
  ui.reader.addEventListener('click', async () => {
    if (!caps.focusMode?.includes('single-shot')) return;
    await apply({ focusMode: 'single-shot' });
    await wait(REFOCUS_MS);
    if (caps.focusMode.includes('continuous')) await apply({ focusMode: 'continuous' });
  });

  return {
    async start() {
      try {
        cameras = await lib.Html5Qrcode.getCameras();
      } catch (err) {
        console.error(err);
        cameras = [];
      }
      const preferred = pickBackCamera(cameras, load(CAMERA_KEY));
      try {
        await startCamera(preferred);
      } catch (err) {
        if (!preferred) throw err;
        console.error('Kamera pilihan gagal, coba bawaan browser', err);
        await startCamera(null);
      }
    },
    pause() {
      if (paused) return;
      try { scanner.pause(true); paused = true; } catch { /* kamera belum siap */ }
    },
    resume() {
      if (!paused) return;
      try { scanner.resume(); paused = false; } catch { /* kamera sudah berhenti */ }
    },
  };
}

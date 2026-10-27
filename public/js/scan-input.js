import { createKeyboardWedge } from './keyboard-wedge.js';

/**
 * Kotak scan yang selalu aktif seperti mesin kasir: scanner mengetik ke kotak lalu Enter,
 * tanpa perlu diklik. Kecepatan ketikan membedakan scanner dari ketikan manual.
 *
 * @param {object} o
 * @param {HTMLFormElement} o.form    form kotak scan (tombol "Kirim" untuk ketikan manual)
 * @param {HTMLInputElement} o.input kotak scan
 * @param {HTMLElement} o.ready      penanda "Siap scan" / "Klik di sini, lalu scan"
 * @param {() => boolean} o.canScan  apakah halaman sedang menerima scan
 * @param {(code: string, manual: boolean) => void} o.onScan
 * @param {(HTMLInputElement|HTMLTextAreaElement)[]} [o.captureFrom] kolom isian lain di halaman
 *   (misalnya catatan): scan yang masuk ke sana dipindah jadi scan, bukan teks
 * @returns {{ focus: () => void }}
 */
export function setupScanInput({ form, input, ready, canScan, onScan, captureFrom = [] }) {
  const inputWedge = createKeyboardWedge();
  const pageWedge = createKeyboardWedge();

  function renderReady() {
    const isReady = canScan() && document.hasFocus() && document.activeElement === input;
    ready.hidden = !canScan();
    ready.classList.toggle('ready', isReady);
    ready.textContent = isReady ? '● Siap scan' : 'Klik di sini, lalu scan';
  }

  function focus() {
    if (canScan() && document.activeElement !== input) input.focus({ preventScroll: true });
    renderReady();
  }

  input.addEventListener('keydown', e => {
    const fastCode = inputWedge.key(e.key, e.timeStamp);
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const code = input.value.trim();
    input.value = '';
    if (code) onScan(code, fastCode === null);
  });
  input.addEventListener('focus', renderReady);
  input.addEventListener('blur', () => setTimeout(renderReady));

  // Cadangan bila fokus sedang di tombol atau bagian lain halaman.
  document.addEventListener('keydown', e => {
    if (e.target.closest('input, textarea, select')) return;
    const code = pageWedge.key(e.key, e.timeStamp);
    if (!code) return;
    e.preventDefault(); // Enter dari scanner tidak boleh menekan tombol yang sedang fokus
    onScan(code, false);
    focus();
  }, true);

  // Petugas yang baru mengetik catatan lalu langsung scan: fokus masih di kolom catatan, jadi
  // ketikan cepat yang diakhiri Enter dibuang dari kolom itu dan diproses sebagai scan.
  for (const field of captureFrom) {
    const wedge = createKeyboardWedge();
    field.addEventListener('keydown', e => {
      const code = wedge.key(e.key, e.timeStamp);
      if (!code) return;
      e.preventDefault();
      field.value = removeScannedText(field.value, code);
      onScan(code, false);
      focus();
    });
  }

  // Tombol "Kirim" untuk kode yang diketik manual.
  form.addEventListener('submit', e => {
    e.preventDefault();
    const code = input.value.trim();
    input.value = '';
    if (code) onScan(code, true);
    focus();
  });

  // Setelah klik di mana pun (kecuali kolom isian), kotak scan aktif lagi.
  document.addEventListener('click', e => {
    if (!e.target.closest('input, textarea, select')) setTimeout(focus);
  });
  window.addEventListener('focus', () => setTimeout(focus));
  window.addEventListener('blur', renderReady);
  ready.addEventListener('click', focus);

  return { focus };
}

/** Buang teks hasil scan (kemunculan terakhir) dari isi kolom; isi lain tidak berubah. */
export function removeScannedText(value, code) {
  const at = value.lastIndexOf(code);
  return at === -1 ? value : value.slice(0, at) + value.slice(at + code.length);
}

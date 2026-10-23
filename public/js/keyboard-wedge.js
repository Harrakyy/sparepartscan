// Scanner barcode USB/Bluetooth bekerja sebagai keyboard: mengetik isi barcode
// sangat cepat lalu menekan Enter. Ketikan manusia jauh lebih lambat, jadi
// jarak waktu antar tombol dipakai untuk membedakan keduanya.
export function createKeyboardWedge({ maxGapMs = 50, minLength = 4, now = () => performance.now() } = {}) {
  let buffer = '';
  let last = 0;

  return {
    // `k` = KeyboardEvent.key. Mengembalikan isi barcode saat scan selesai, selain itu null.
    key(k) {
      const t = now();
      const fast = buffer && t - last <= maxGapMs;
      if (k === 'Enter') {
        const code = fast && buffer.length >= minLength ? buffer : null;
        buffer = '';
        return code;
      }
      if (k.length !== 1) return null; // Shift, Control, dll.
      buffer = fast ? buffer + k : k;
      last = t;
      return null;
    },
  };
}

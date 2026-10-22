const GTIN_LENGTHS = new Set([8, 12, 13, 14]);
const CHECKED_FORMATS = new Set(['EAN_13', 'EAN_8', 'UPC_A']);

// Digit cek GS1: dari kanan, bobot 3,1,3,... untuk digit data.
export function isValidGtin(code) {
  if (!/^\d+$/.test(code) || !GTIN_LENGTHS.has(code.length)) return false;
  const digits = [...code].map(Number);
  const check = digits.pop();
  const sum = digits.reverse().reduce((acc, d, i) => acc + d * (i % 2 === 0 ? 3 : 1), 0);
  return (10 - (sum % 10)) % 10 === check;
}

export function hasCheckDigit(formatName) {
  return CHECKED_FORMATS.has(formatName);
}

// null = bacaan ditolak (digit cek salah). UPC-A disamakan ke bentuk EAN-13
// supaya Android dan iPhone menghasilkan barcode yang sama.
export function normalizeBarcode(text, formatName) {
  const code = String(text).trim();
  if (!code) return null;
  if (!CHECKED_FORMATS.has(formatName)) return code;
  if (!isValidGtin(code)) return null;
  return formatName === 'UPC_A' ? `0${code}` : code;
}

// Baru diterima setelah kode yang sama terbaca `required` kali berturut-turut,
// masing-masing berjarak kurang dari `windowMs`. Mencegah salah baca sekali lewat.
export function createReadConfirmer({ required = 3, windowMs = 1500, now = Date.now } = {}) {
  let last = '';
  let count = 0;
  let at = 0;
  return code => {
    const t = now();
    count = code === last && t - at < windowMs ? count + 1 : 1;
    last = code;
    at = t;
    if (count < required) return false;
    last = '';
    count = 0;
    return true;
  };
}

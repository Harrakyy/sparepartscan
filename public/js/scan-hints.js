const SMALL_RATIO = 0.25;
const DARK_LUMA = 40;

// BarcodeDetector memakai 'ean_13'; barcode.js memakai 'EAN_13'.
export function toFormatName(format) {
  return format ? format.toUpperCase() : undefined;
}

export function isSmallInFrame(box, frameWidth) {
  if (!box || !frameWidth) return false;
  return box.width / frameWidth < SMALL_RATIO;
}

// `data` = piksel RGBA dari gambar kamera yang diperkecil.
export function isDarkFrame(data) {
  const pixels = data.length / 4;
  if (!pixels) return false;
  let sum = 0;
  for (let i = 0; i < data.length; i += 4) sum += 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
  return sum / pixels < DARK_LUMA;
}

export function clampZoom(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

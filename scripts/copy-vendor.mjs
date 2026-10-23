// Menyalin library pembaca faktur dari node_modules ke public/vendor supaya bisa
// dipakai tanpa CDN dan tanpa build di hosting. Jalankan: npm run vendor
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const copies = [
  ['node_modules/pdfjs-dist/build/pdf.min.mjs', 'public/vendor/pdfjs/pdf.min.mjs'],
  ['node_modules/pdfjs-dist/build/pdf.worker.min.mjs', 'public/vendor/pdfjs/pdf.worker.min.mjs'],
  ['node_modules/tesseract.js/dist/tesseract.min.js', 'public/vendor/tesseract/tesseract.min.js'],
  ['node_modules/tesseract.js/dist/worker.min.js', 'public/vendor/tesseract/worker.min.js'],
  // Mode LSTM saja (OEM 1): cukup varian *-lstm; Tesseract memilih sendiri sesuai dukungan SIMD browser.
  ['node_modules/tesseract.js-core/tesseract-core-lstm.wasm.js', 'public/vendor/tesseract/core/tesseract-core-lstm.wasm.js'],
  ['node_modules/tesseract.js-core/tesseract-core-simd-lstm.wasm.js', 'public/vendor/tesseract/core/tesseract-core-simd-lstm.wasm.js'],
  ['node_modules/tesseract.js-core/tesseract-core-relaxedsimd-lstm.wasm.js', 'public/vendor/tesseract/core/tesseract-core-relaxedsimd-lstm.wasm.js'],
  ['node_modules/@tesseract.js-data/eng/4.0.0_best_int/eng.traineddata.gz', 'public/vendor/tesseract/lang/eng.traineddata.gz'],
];

for (const [from, to] of copies) {
  fs.mkdirSync(path.dirname(path.join(root, to)), { recursive: true });
  fs.copyFileSync(path.join(root, from), path.join(root, to));
  console.log(`${from} -> ${to}`);
}

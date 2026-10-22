import express from 'express';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { partsRouter } from './routes/parts.js';
import { movementsRouter } from './routes/movements.js';
import { fail } from './respond.js';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const VENDOR_FILES = {
  // Polyfill BarcodeDetector untuk browser yang belum punya (Safari iPhone, Firefox).
  '/vendor/barcode-detector.js': path.join(ROOT, 'node_modules', 'barcode-detector', 'dist', 'iife', 'polyfill.js'),
  '/vendor/zxing_reader.wasm': path.join(ROOT, 'node_modules', 'zxing-wasm', 'dist', 'reader', 'zxing_reader.wasm'),
};

export function createApp({ db }) {
  const app = express();
  app.disable('x-powered-by');
  app.use(express.json({ limit: '10kb' }));

  app.use('/api/parts', partsRouter(db));
  app.use('/api/movements', movementsRouter(db));
  app.use('/api', (req, res) => fail(res, 404, 'Alamat API tidak ditemukan'));

  for (const [route, file] of Object.entries(VENDOR_FILES)) {
    app.get(route, (req, res) => res.sendFile(file));
  }
  app.use(express.static(path.join(ROOT, 'public')));

  // eslint-disable-next-line no-unused-vars
  app.use((err, req, res, next) => {
    if (err.type === 'entity.parse.failed') return fail(res, 400, 'Format data tidak valid');
    if (err.type === 'entity.too.large') return fail(res, 413, 'Data terlalu besar');
    console.error(err);
    return fail(res, 500, 'Terjadi kesalahan di server. Coba lagi.');
  });

  return app;
}

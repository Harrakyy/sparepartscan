import express from 'express';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { partsRouter } from './routes/parts.js';
import { movementsRouter } from './routes/movements.js';
import { fail } from './respond.js';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const SCANNER_LIB = path.join(ROOT, 'node_modules', 'html5-qrcode', 'html5-qrcode.min.js');

export function createApp({ db }) {
  const app = express();
  app.disable('x-powered-by');
  app.use(express.json({ limit: '10kb' }));

  app.use('/api/parts', partsRouter(db));
  app.use('/api/movements', movementsRouter(db));
  app.use('/api', (req, res) => fail(res, 404, 'Alamat API tidak ditemukan'));

  app.get('/vendor/html5-qrcode.min.js', (req, res) => res.sendFile(SCANNER_LIB));
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

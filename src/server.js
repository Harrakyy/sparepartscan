import fs from 'node:fs';
import path from 'node:path';
import { openDb } from './db.js';
import { createApp } from './app.js';

const PORT = Number(process.env.PORT) || 3000;
const DB_PATH = process.env.DB_PATH || 'data/sparepart.db';

fs.mkdirSync(path.dirname(path.resolve(DB_PATH)), { recursive: true });
const db = openDb(DB_PATH);

// Hanya localhost: HP masuk lewat cloudflared, bukan langsung dari WiFi toko.
createApp({ db }).listen(PORT, '127.0.0.1', () => {
  console.log(`Server jalan di http://localhost:${PORT}`);
});

import fs from 'node:fs';
import path from 'node:path';
import { openDb } from './db.js';
import { createApp } from './app.js';

const PORT = Number(process.env.PORT) || 3000;
const DB_PATH = process.env.DB_PATH || 'data/sparepart.db';

fs.mkdirSync(path.dirname(path.resolve(DB_PATH)), { recursive: true });
const db = openDb(DB_PATH);

createApp({ db }).listen(PORT, () => {
  console.log(`Server jalan di http://localhost:${PORT}`);
});

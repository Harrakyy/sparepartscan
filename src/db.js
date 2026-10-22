import { DatabaseSync } from 'node:sqlite';

const SCHEMA = `
CREATE TABLE IF NOT EXISTS parts (
  id INTEGER PRIMARY KEY,
  barcode TEXT NOT NULL UNIQUE,
  part_number TEXT NOT NULL,
  name TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  price INTEGER NOT NULL DEFAULT 0,
  stock INTEGER NOT NULL DEFAULT 0 CHECK (stock >= 0),
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS movements (
  id INTEGER PRIMARY KEY,
  part_id INTEGER NOT NULL REFERENCES parts(id),
  type TEXT NOT NULL CHECK (type IN ('in', 'out')),
  qty INTEGER NOT NULL CHECK (qty > 0),
  stock_after INTEGER NOT NULL,
  note TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_movements_created_at ON movements(created_at);
`;

// Versi awal punya tiga harga dan deskripsi wajib (yang isinya nama barang).
// Sekarang: nama wajib, deskripsi opsional, satu harga.
function migrateThreePrices(db) {
  const columns = db.prepare('PRAGMA table_info(parts)').all().map(c => c.name);
  if (!columns.includes('price_retail')) return;
  db.exec('BEGIN');
  try {
    db.exec(`
      ALTER TABLE parts ADD COLUMN name TEXT NOT NULL DEFAULT '';
      UPDATE parts SET name = description, description = '';
      ALTER TABLE parts RENAME COLUMN price_retail TO price;
      ALTER TABLE parts DROP COLUMN price_member;
      ALTER TABLE parts DROP COLUMN price_wholesale;
    `);
    db.exec('COMMIT');
  } catch (err) {
    db.exec('ROLLBACK');
    throw new Error(`Gagal memperbarui struktur database: ${err.message}`);
  }
}

export function openDb(path) {
  const db = new DatabaseSync(path);
  db.exec('PRAGMA foreign_keys = ON');
  if (path !== ':memory:') db.exec('PRAGMA journal_mode = WAL');
  db.exec(SCHEMA);
  migrateThreePrices(db);
  return db;
}

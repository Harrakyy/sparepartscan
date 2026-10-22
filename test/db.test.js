import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { DatabaseSync } from 'node:sqlite';
import { openDb } from '../src/db.js';

test('openDb creates parts and movements tables', () => {
  const db = openDb(':memory:');
  const names = db.prepare("SELECT name FROM sqlite_master WHERE type = 'table'").all().map(r => r.name);
  assert.ok(names.includes('parts'));
  assert.ok(names.includes('movements'));
  db.close();
});

test('openDb migrates the old three-price schema to name + price', () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'sparepart-'));
  const file = path.join(dir, 'old.db');
  const old = new DatabaseSync(file);
  old.exec(`CREATE TABLE parts (
    id INTEGER PRIMARY KEY, barcode TEXT NOT NULL UNIQUE, part_number TEXT NOT NULL, description TEXT NOT NULL,
    price_retail INTEGER NOT NULL DEFAULT 0, price_member INTEGER NOT NULL DEFAULT 0, price_wholesale INTEGER NOT NULL DEFAULT 0,
    stock INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)`);
  old.prepare(`INSERT INTO parts (barcode, part_number, description, price_retail, price_member, price_wholesale, stock, created_at, updated_at)
    VALUES ('899', 'BP-01', 'Kampas rem', 150000, 140000, 130000, 4, 't', 't')`).run();
  old.close();

  const db = openDb(file);
  const row = db.prepare('SELECT * FROM parts').get();
  assert.equal(row.name, 'Kampas rem');
  assert.equal(row.description, '');
  assert.equal(row.price, 150000);
  assert.equal(row.stock, 4);
  assert.equal('price_member' in row, false);
  db.close();
  openDb(file).close(); // migrasi tidak jalan dua kali
  fs.rmSync(dir, { recursive: true, force: true });
});

test('openDb can reopen an existing file', () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'sparepart-'));
  const file = path.join(dir, 'test.db');
  openDb(file).close();
  const db = openDb(file);
  assert.equal(db.prepare('SELECT COUNT(*) AS n FROM parts').get().n, 0);
  db.close();
  fs.rmSync(dir, { recursive: true, force: true });
});

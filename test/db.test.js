import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { openDb } from '../src/db.js';

test('openDb creates parts and movements tables', () => {
  const db = openDb(':memory:');
  const names = db.prepare("SELECT name FROM sqlite_master WHERE type = 'table'").all().map(r => r.name);
  assert.ok(names.includes('parts'));
  assert.ok(names.includes('movements'));
  db.close();
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

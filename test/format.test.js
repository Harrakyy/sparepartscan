import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatRupiah, formatWib } from '../public/js/format.js';

test('formatRupiah uses Indonesian thousands separator', () => {
  assert.equal(formatRupiah(150000).replace(/\s/g, ' '), 'Rp 150.000');
  assert.equal(formatRupiah(0).replace(/\s/g, ' '), 'Rp 0');
});

test('formatWib converts UTC to Asia/Jakarta', () => {
  assert.equal(formatWib('2026-10-22T07:05:00.000Z'), '22/10/2026 14:05 WIB');
});

test('formatWib rolls over to the next day', () => {
  assert.equal(formatWib('2026-10-22T17:30:00.000Z'), '23/10/2026 00:30 WIB');
});

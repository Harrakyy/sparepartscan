import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatRupiah, formatWib, parseWholeNumber } from '../public/js/format.js';

test('parseWholeNumber reads Indonesian thousands separators', () => {
  assert.equal(parseWholeNumber('50.000'), 50000);
  assert.equal(parseWholeNumber(' 1.250.000 '), 1250000);
  assert.equal(parseWholeNumber('450000'), 450000);
  assert.equal(parseWholeNumber('0'), 0);
});

test('parseWholeNumber rejects empty, decimal comma, and non-digits', () => {
  for (const raw of ['', '   ', '12,5', 'abc', '-5', '1e3']) {
    assert.ok(Number.isNaN(parseWholeNumber(raw)), raw);
  }
});

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

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { removeScannedText } from '../public/js/scan-input.js';

test('a scan typed at the end of a text field is removed from it', () => {
  assert.equal(removeScannedText('salah kirim tipeS-SCS-002', 'S-SCS-002'), 'salah kirim tipe');
});

test('a scan typed in the middle of a text field is removed from that spot', () => {
  assert.equal(removeScannedText('rusak S-SCS-002di jalan', 'S-SCS-002'), 'rusak di jalan');
});

test('text without the scanned code is left alone', () => {
  assert.equal(removeScannedText('rusak di jalan', 'S-SCS-002'), 'rusak di jalan');
});

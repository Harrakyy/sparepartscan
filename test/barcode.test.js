import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isValidGtin, normalizeBarcode, createReadConfirmer, hasCheckDigit } from '../public/js/barcode.js';

test('hasCheckDigit is true only for EAN/UPC-A', () => {
  assert.equal(hasCheckDigit('EAN_13'), true);
  assert.equal(hasCheckDigit('UPC_A'), true);
  assert.equal(hasCheckDigit('CODE_128'), false);
  assert.equal(hasCheckDigit(undefined), false);
});

test('isValidGtin accepts correct EAN-13, EAN-8, and UPC-A check digits', () => {
  assert.equal(isValidGtin('8991234567891'), true);
  assert.equal(isValidGtin('0012345678905'), true);
  assert.equal(isValidGtin('96385074'), true);
  assert.equal(isValidGtin('036000291452'), true);
});

test('isValidGtin rejects a single misread digit', () => {
  assert.equal(isValidGtin('8991234567892'), false);
  assert.equal(isValidGtin('8991234567391'), false);
});

test('isValidGtin rejects non-digits and wrong lengths', () => {
  for (const code of ['', 'ABC1234567890', '12345', '899123456789112']) {
    assert.equal(isValidGtin(code), false, code);
  }
});

test('normalizeBarcode drops EAN/UPC reads with a bad check digit', () => {
  assert.equal(normalizeBarcode('8991234567892', 'EAN_13'), null);
  assert.equal(normalizeBarcode('96385075', 'EAN_8'), null);
});

test('normalizeBarcode keeps valid EAN-13 as is', () => {
  assert.equal(normalizeBarcode(' 8991234567891 ', 'EAN_13'), '8991234567891');
});

test('normalizeBarcode turns UPC-A into its 13-digit EAN form', () => {
  assert.equal(normalizeBarcode('036000291452', 'UPC_A'), '0036000291452');
});

test('normalizeBarcode leaves Code 128 and QR text untouched', () => {
  assert.equal(normalizeBarcode('04465-0K240', 'CODE_128'), '04465-0K240');
  assert.equal(normalizeBarcode('PART-123', 'QR_CODE'), 'PART-123');
});

test('normalizeBarcode rejects empty text', () => {
  assert.equal(normalizeBarcode('   ', 'CODE_128'), null);
});

test('confirmer accepts a code only after N identical reads in a row', () => {
  let t = 0;
  const confirm = createReadConfirmer({ required: 3, windowMs: 1500, now: () => t });
  assert.equal(confirm('A'), false);
  t += 100; assert.equal(confirm('A'), false);
  t += 100; assert.equal(confirm('A'), true);
});

test('confirmer restarts when a different code appears', () => {
  let t = 0;
  const confirm = createReadConfirmer({ required: 3, windowMs: 1500, now: () => t });
  confirm('A'); t += 100; confirm('A');
  t += 100; assert.equal(confirm('B'), false);
  t += 100; assert.equal(confirm('A'), false);
});

test('confirmer restarts when reads are too far apart', () => {
  let t = 0;
  const confirm = createReadConfirmer({ required: 2, windowMs: 1500, now: () => t });
  confirm('A');
  t += 2000; assert.equal(confirm('A'), false);
  t += 100; assert.equal(confirm('A'), true);
});

test('confirmer starts fresh after confirming', () => {
  let t = 0;
  const confirm = createReadConfirmer({ required: 2, windowMs: 1500, now: () => t });
  confirm('A'); t += 100; assert.equal(confirm('A'), true);
  t += 100; assert.equal(confirm('A'), false);
});

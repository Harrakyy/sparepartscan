import { test } from 'node:test';
import assert from 'node:assert/strict';
import { validatePartCreate, validatePartUpdate, validateMovement, parseLimit } from '../src/validation.js';

const valid = {
  barcode: '0012345678905',
  part_number: 'BP-01',
  name: 'Kampas rem',
  description: 'Untuk Innova Reborn',
  price: 150000,
};
const { barcode: _omit, ...validWithoutBarcode } = valid;

test('part create: trims and keeps leading zeros of barcode', () => {
  const r = validatePartCreate({ ...valid, barcode: '  0012345678905 ' });
  assert.equal(r.ok, true);
  assert.equal(r.value.barcode, '0012345678905');
});

test('part create: rejects empty barcode', () => {
  assert.equal(validatePartCreate({ ...valid, barcode: '' }).ok, false);
});

test('part create: rejects barcode longer than 64', () => {
  assert.equal(validatePartCreate({ ...valid, barcode: 'x'.repeat(65) }).ok, false);
});

test('part create: name is required', () => {
  const r = validatePartCreate({ ...valid, name: '  ' });
  assert.equal(r.ok, false);
  assert.match(r.error, /Nama barang/);
});

test('part create: rejects name longer than 200', () => {
  assert.equal(validatePartCreate({ ...valid, name: 'x'.repeat(201) }).ok, false);
});

test('part create: description is optional and defaults to empty', () => {
  const { description: _d, ...withoutDescription } = valid;
  const r = validatePartCreate(withoutDescription);
  assert.equal(r.ok, true);
  assert.equal(r.value.description, '');
});

test('part create: price is required', () => {
  const { price: _p, ...withoutPrice } = valid;
  const r = validatePartCreate(withoutPrice);
  assert.equal(r.ok, false);
  assert.match(r.error, /Harga/);
});

test('part create: value contains only the new fields', () => {
  const r = validatePartCreate({ ...valid, price_member: 1 });
  assert.deepEqual(Object.keys(r.value).sort(), ['barcode', 'description', 'name', 'part_number', 'price']);
});

test('part create: rejects description longer than 500', () => {
  assert.equal(validatePartCreate({ ...valid, description: 'x'.repeat(501) }).ok, false);
});

test('part create: rejects non-integer price', () => {
  assert.equal(validatePartCreate({ ...valid, price: 1.5 }).ok, false);
});

test('part create: rejects numeric string price', () => {
  assert.equal(validatePartCreate({ ...valid, price: '5000' }).ok, false);
});

test('part create: rejects negative price', () => {
  assert.equal(validatePartCreate({ ...valid, price: -1 }).ok, false);
});

test('part create: rejects price above 1.000.000.000', () => {
  assert.equal(validatePartCreate({ ...valid, price: 1_000_000_001 }).ok, false);
});

test('part create: error message names the field', () => {
  const r = validatePartCreate({ ...valid, part_number: '' });
  assert.equal(r.ok, false);
  assert.match(r.error, /Part number/);
});

test('part update: ignores barcode and stock', () => {
  const r = validatePartUpdate({ ...validWithoutBarcode, barcode: 'X', stock: 99 });
  assert.equal(r.ok, true);
  assert.equal('barcode' in r.value, false);
  assert.equal('stock' in r.value, false);
});

test('movement: valid input defaults note to empty string', () => {
  const r = validateMovement({ part_id: 1, type: 'in', qty: 5 });
  assert.equal(r.ok, true);
  assert.equal(r.value.note, '');
});

test('movement: rejects unknown type', () => {
  assert.equal(validateMovement({ part_id: 1, type: 'adjust', qty: 5 }).ok, false);
});

test('movement: rejects qty 0', () => {
  assert.equal(validateMovement({ part_id: 1, type: 'out', qty: 0 }).ok, false);
});

test('movement: rejects qty above 100.000', () => {
  assert.equal(validateMovement({ part_id: 1, type: 'out', qty: 100_001 }).ok, false);
});

test('movement: rejects note longer than 200', () => {
  assert.equal(validateMovement({ part_id: 1, type: 'out', qty: 1, note: 'x'.repeat(201) }).ok, false);
});

test('movement: rejects null body without throwing', () => {
  assert.equal(validateMovement(null).ok, false);
});

test('parseLimit: defaults, clamps, and parses', () => {
  assert.equal(parseLimit(undefined, 100, 500), 100);
  assert.equal(parseLimit('abc', 100, 500), 100);
  assert.equal(parseLimit('0', 100, 500), 100);
  assert.equal(parseLimit('999', 100, 500), 500);
  assert.equal(parseLimit('20', 100, 500), 20);
});

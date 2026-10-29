import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readOutQty } from '../public/js/pages/session-admin.js';

test('a whole number from 0 to the invoice quantity is accepted', () => {
  assert.equal(readOutQty('1', 2), 1);
  assert.equal(readOutQty(' 0 ', 2), 0);
  assert.equal(readOutQty('2', 2), 2);
});

test('an empty or invalid field is not silently turned into 0', () => {
  assert.equal(readOutQty('', 2), null);
  assert.equal(readOutQty('   ', 2), null);
  assert.equal(readOutQty('1.5', 2), null);
  assert.equal(readOutQty('-1', 2), null);
  assert.equal(readOutQty('3', 2), null);
  assert.equal(readOutQty('abc', 2), null);
});

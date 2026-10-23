import { test } from 'node:test';
import assert from 'node:assert/strict';
import { editDistance, suggestCode } from '../public/js/similar-code.js';

const invoiceCodes = ['S-SCS-002', 'H-KTE-001', 'S-MCS-005', 'H-MYJ-005', 'H-MTE-009/010', 'H-SYJ-002', 'H-TCB-N13', 'S-TKS-002', 'S-EFP-EP-T11'];

test('editDistance counts single-character edits', () => {
  assert.equal(editDistance('S-SCS-002', 'S-MCS-005'), 2);
  assert.equal(editDistance('abc', 'abc'), 0);
  assert.equal(editDistance('', 'abc'), 3);
});

test('suggestCode returns the closest invoice code', () => {
  assert.equal(suggestCode('S-TKS-003', invoiceCodes), 'S-TKS-002');
  assert.equal(suggestCode('H-SYJ-003', invoiceCodes), 'H-SYJ-002');
});

test('suggestCode returns null when nothing is close enough', () => {
  assert.equal(suggestCode('ZZZ', invoiceCodes), null);
  assert.equal(suggestCode('S-TKS-002', []), null);
});

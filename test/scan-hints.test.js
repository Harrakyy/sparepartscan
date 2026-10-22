import { test } from 'node:test';
import assert from 'node:assert/strict';
import { toFormatName, isSmallInFrame, isDarkFrame, clampZoom } from '../public/js/scan-hints.js';

test('toFormatName maps BarcodeDetector formats to our names', () => {
  assert.equal(toFormatName('ean_13'), 'EAN_13');
  assert.equal(toFormatName('upc_a'), 'UPC_A');
  assert.equal(toFormatName(undefined), undefined);
});

test('isSmallInFrame is true when the barcode is under a quarter of the frame width', () => {
  assert.equal(isSmallInFrame({ width: 300 }, 1280), true);
  assert.equal(isSmallInFrame({ width: 320 }, 1280), false);
  assert.equal(isSmallInFrame(null, 1280), false);
  assert.equal(isSmallInFrame({ width: 10 }, 0), false);
});

function frame(luma, pixels = 4) {
  const data = new Uint8ClampedArray(pixels * 4);
  for (let i = 0; i < data.length; i += 4) data.set([luma, luma, luma, 255], i);
  return data;
}

test('isDarkFrame flags a frame with low average brightness', () => {
  assert.equal(isDarkFrame(frame(20)), true);
  assert.equal(isDarkFrame(frame(120)), false);
});

test('isDarkFrame treats an empty frame as not dark', () => {
  assert.equal(isDarkFrame(new Uint8ClampedArray(0)), false);
});

test('clampZoom keeps zoom inside the camera range', () => {
  assert.equal(clampZoom(10, 1, 8), 8);
  assert.equal(clampZoom(0.5, 1, 8), 1);
  assert.equal(clampZoom(2.5, 1, 8), 2.5);
});

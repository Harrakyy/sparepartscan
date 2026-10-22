import { test } from 'node:test';
import assert from 'node:assert/strict';
import { pickBackCamera, nextCameraId } from '../public/js/camera.js';

const android = [
  { id: 'a0', label: 'camera2 0, facing back' },
  { id: 'a1', label: 'camera2 1, facing front' },
  { id: 'a2', label: 'camera2 2, facing back' },
  { id: 'a3', label: 'camera2 3, facing back' },
];

const iphonePro = [
  { id: 'i0', label: 'Front Camera' },
  { id: 'i1', label: 'Back Ultra Wide Camera' },
  { id: 'i2', label: 'Back Camera' },
  { id: 'i3', label: 'Back Telephoto Camera' },
  { id: 'i4', label: 'Back Triple Camera' },
];

test('pickBackCamera prefers the saved camera when it still exists', () => {
  assert.equal(pickBackCamera(android, 'a2'), 'a2');
});

test('pickBackCamera ignores a saved camera that is gone', () => {
  assert.equal(pickBackCamera(android, 'gone'), 'a0');
});

test('pickBackCamera picks the Android main back camera (camera2 0)', () => {
  const shuffled = [android[2], android[1], android[0]];
  assert.equal(pickBackCamera(shuffled), 'a0');
});

test('pickBackCamera picks the iPhone virtual triple/dual camera for auto macro', () => {
  assert.equal(pickBackCamera(iphonePro), 'i4');
  const dual = [{ id: 'd1', label: 'Back Ultra Wide Camera' }, { id: 'd2', label: 'Back Dual Wide Camera' }];
  assert.equal(pickBackCamera(dual), 'd2');
});

test('pickBackCamera avoids ultra wide and telephoto when a plain back camera exists', () => {
  const cams = [{ id: 'u', label: 'Back Ultra Wide Camera' }, { id: 'b', label: 'Back Camera' }, { id: 't', label: 'Back Telephoto Camera' }];
  assert.equal(pickBackCamera(cams), 'b');
});

test('pickBackCamera falls back to the last camera when labels say nothing', () => {
  assert.equal(pickBackCamera([{ id: 'x', label: 'USB Cam' }, { id: 'y', label: 'Integrated' }]), 'y');
});

test('pickBackCamera returns null for no cameras or empty labels', () => {
  assert.equal(pickBackCamera([]), null);
  assert.equal(pickBackCamera([{ id: 'x', label: '' }, { id: 'y', label: '' }]), null);
});

test('nextCameraId cycles through back cameras only', () => {
  assert.equal(nextCameraId(android, 'a0'), 'a2');
  assert.equal(nextCameraId(android, 'a2'), 'a3');
  assert.equal(nextCameraId(android, 'a3'), 'a0');
});

test('nextCameraId cycles through all cameras when none is labelled back', () => {
  const cams = [{ id: 'x', label: 'USB Cam' }, { id: 'y', label: 'Integrated' }];
  assert.equal(nextCameraId(cams, 'y'), 'x');
});

test('nextCameraId starts at the first camera when the current one is unknown', () => {
  assert.equal(nextCameraId(android, null), 'a0');
});

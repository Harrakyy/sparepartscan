import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createKeyboardWedge } from '../public/js/keyboard-wedge.js';

function typer(gapMs) {
  let t = 0;
  const wedge = createKeyboardWedge({ maxGapMs: 50, minLength: 4, now: () => t });
  return {
    type(text) {
      let result = null;
      for (const ch of text) {
        result = wedge.key(ch);
        t += gapMs;
      }
      return result;
    },
    key(k) { return wedge.key(k); },
    wait(ms) { t += ms; },
  };
}

test('fast keystrokes ending in Enter are read as a scan', () => {
  const s = typer(10);
  s.type('8999999580773');
  assert.equal(s.key('Enter'), '8999999580773');
});

test('slow human typing is not a scan', () => {
  const s = typer(200);
  s.type('8999999580773');
  assert.equal(s.key('Enter'), null);
});

test('a too-short burst is not a scan', () => {
  const s = typer(10);
  s.type('12');
  assert.equal(s.key('Enter'), null);
});

test('a pause before the scan drops the earlier stray keys', () => {
  const s = typer(10);
  s.type('ab');
  s.wait(1000);
  s.type('04465-0K240');
  assert.equal(s.key('Enter'), '04465-0K240');
});

test('modifier keys like Shift do not break a burst', () => {
  const s = typer(10);
  s.type('PART');
  s.key('Shift');
  s.type('-12');
  assert.equal(s.key('Enter'), 'PART-12');
});

test('the buffer resets after a scan', () => {
  const s = typer(10);
  s.type('11112222');
  s.key('Enter');
  s.type('33');
  assert.equal(s.key('Enter'), null);
});

test('event timestamps are used when given, so a slow handler does not cut a scan', () => {
  let clock = 0;
  const wedge = createKeyboardWedge({ maxGapMs: 50, minLength: 4, now: () => clock });
  // Tombol ditekan tiap 10 ms, tapi pemrosesannya sempat tertunda 300 ms di tengah.
  [...'S-SCS-002'].forEach((ch, i) => {
    clock = i < 4 ? i * 10 : 300 + i * 10;
    wedge.key(ch, i * 10);
  });
  clock = 400;
  assert.equal(wedge.key('Enter', 90), 'S-SCS-002');
});

test('a slow Enter after a fast burst is not a scan', () => {
  const s = typer(10);
  s.type('8999999580773');
  s.wait(500);
  assert.equal(s.key('Enter'), null);
});

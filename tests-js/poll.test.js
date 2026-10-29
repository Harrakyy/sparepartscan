import { test, mock } from 'node:test';
import assert from 'node:assert/strict';
import { everyVisible } from '../public/js/poll.js';

function fakeDocument(state) {
  const listeners = [];
  return {
    visibilityState: state,
    addEventListener: (_, fn) => listeners.push(fn),
    removeEventListener: (_, fn) => listeners.splice(listeners.indexOf(fn), 1),
    show() { this.visibilityState = 'visible'; listeners.forEach(fn => fn()); },
  };
}

test('runs every interval while the tab is visible', () => {
  mock.timers.enable({ apis: ['setInterval'] });
  let runs = 0;
  const stop = everyVisible(5000, () => runs++, fakeDocument('visible'));
  mock.timers.tick(15000);
  stop();
  mock.timers.reset();
  assert.equal(runs, 3);
});

test('skips while hidden and refreshes as soon as the tab is shown again', () => {
  mock.timers.enable({ apis: ['setInterval'] });
  let runs = 0;
  const doc = fakeDocument('hidden');
  const stop = everyVisible(5000, () => runs++, doc);
  mock.timers.tick(15000);
  assert.equal(runs, 0);
  doc.show();
  assert.equal(runs, 1);
  stop();
  mock.timers.reset();
});

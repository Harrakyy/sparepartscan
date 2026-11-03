import { test, before } from 'node:test';
import assert from 'node:assert/strict';

// el() memakai document.createElement; Node tanpa DOM, jadi stub minimal yang cukup untuk el().
before(() => {
  globalThis.document = {
    createElement(tag) {
      return { tagName: tag.toUpperCase(), className: '', textContent: undefined };
    },
  };
});

const { el } = await import('../public/js/dom.js');

test('el sets tag, class and text', () => {
  const node = el('span', 'x', 'hi');
  assert.equal(node.tagName, 'SPAN');
  assert.equal(node.className, 'x');
  assert.equal(node.textContent, 'hi');
});

test('el leaves class and text untouched when omitted', () => {
  const node = el('div');
  assert.equal(node.tagName, 'DIV');
  assert.equal(node.className, '');
  assert.equal(node.textContent, undefined);
});

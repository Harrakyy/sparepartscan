import { test, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { startTestServer, request, samplePart } from './helpers.js';

let srv;
let partId;
beforeEach(async () => {
  srv = await startTestServer();
  partId = (await request(srv.baseUrl, 'POST', '/api/parts', samplePart)).body.data.id;
});
afterEach(async () => { await srv.close(); });

const move = (type, qty, extra = {}) =>
  request(srv.baseUrl, 'POST', '/api/movements', { part_id: partId, type, qty, ...extra });
const history = (query = '') => request(srv.baseUrl, 'GET', '/api/movements' + query);

test('in, out, and insufficient out update stock correctly', async () => {
  const a = await move('in', 5);
  assert.equal(a.status, 201);
  assert.equal(a.body.data.part.stock, 5);
  assert.equal(a.body.data.movement.stock_after, 5);

  const b = await move('out', 2);
  assert.equal(b.status, 201);
  assert.equal(b.body.data.part.stock, 3);

  const c = await move('out', 10);
  assert.equal(c.status, 409);
  assert.equal(c.body.error, 'Stok tidak cukup (stok: 3)');

  const part = await request(srv.baseUrl, 'GET', '/api/parts/by-barcode/' + samplePart.barcode);
  assert.equal(part.body.data.stock, 3);
});

test('second out exceeding remaining stock is rejected and not recorded', async () => {
  await move('in', 3);
  assert.equal((await move('out', 2)).status, 201);
  assert.equal((await move('out', 2)).status, 409);
  const h = await history();
  assert.equal(h.body.data.length, 2);
  assert.deepEqual(h.body.data.map(m => [m.type, m.qty]), [['out', 2], ['in', 3]]);
  const part = await request(srv.baseUrl, 'GET', '/api/parts/by-barcode/' + samplePart.barcode);
  assert.equal(part.body.data.stock, 1);
});

test('unknown part returns 404', async () => {
  const r = await request(srv.baseUrl, 'POST', '/api/movements', { part_id: 9999, type: 'in', qty: 1 });
  assert.equal(r.status, 404);
  assert.equal(r.body.success, false);
});

test('unknown part on out returns 404, not insufficient stock', async () => {
  const r = await request(srv.baseUrl, 'POST', '/api/movements', { part_id: 9999, type: 'out', qty: 1 });
  assert.equal(r.status, 404);
});

test('qty 0 returns 400', async () => {
  assert.equal((await move('in', 0)).status, 400);
});

test('history limit returns newest with part_number', async () => {
  await move('in', 5, { note: 'dari supplier' });
  await move('out', 1);
  const r = await history('?limit=1');
  assert.equal(r.body.data.length, 1);
  assert.equal(r.body.data[0].type, 'out');
  assert.equal(r.body.data[0].part_number, samplePart.part_number);
});

test('history limit above max is clamped, not an error', async () => {
  await move('in', 1);
  const r = await history('?limit=9999');
  assert.equal(r.status, 200);
  assert.equal(r.body.data.length, 1);
});

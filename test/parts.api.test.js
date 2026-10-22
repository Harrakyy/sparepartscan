import { test, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { startTestServer, request, samplePart } from './helpers.js';

let srv;
beforeEach(async () => { srv = await startTestServer(); });
afterEach(async () => { await srv.close(); });

const call = (method, path, body) => request(srv.baseUrl, method, path, body);
const create = overrides => call('POST', '/api/parts', { ...samplePart, ...overrides });

test('POST /api/parts creates a part with stock 0 and keeps leading zeros', async () => {
  const r = await create();
  assert.equal(r.status, 201);
  assert.equal(r.body.success, true);
  assert.equal(r.body.data.stock, 0);
  assert.equal(r.body.data.barcode, '0012345678905');
});

test('POST /api/parts with duplicate barcode returns 409', async () => {
  await create();
  const r = await create();
  assert.equal(r.status, 409);
  assert.equal(r.body.success, false);
  assert.match(r.body.error, /sudah terdaftar/);
});

test('POST /api/parts with non-integer price returns 400', async () => {
  const r = await create({ price_retail: 1.5 });
  assert.equal(r.status, 400);
  assert.equal(r.body.success, false);
});

test('GET by-barcode finds exact barcode only', async () => {
  await create();
  const found = await call('GET', '/api/parts/by-barcode/0012345678905');
  assert.equal(found.status, 200);
  assert.equal(found.body.data.part_number, 'BP-01');
  const missing = await call('GET', '/api/parts/by-barcode/12345678905');
  assert.equal(missing.status, 404);
  assert.equal(missing.body.success, false);
});

test('GET /api/parts?q= matches description case-insensitively', async () => {
  await create();
  const r = await call('GET', '/api/parts?q=kampas');
  assert.equal(r.body.data.length, 1);
});

test('GET /api/parts?q= treats % literally', async () => {
  await create({ barcode: '1', description: 'Diskon 50% oli' });
  await create({ barcode: '2', description: 'Filter udara' });
  const r = await call('GET', '/api/parts?q=' + encodeURIComponent('50%'));
  assert.deepEqual(r.body.data.map(p => p.barcode), ['1']);
});

test('GET /api/parts?q= treats _ literally', async () => {
  await create({ barcode: '1', description: 'axb' });
  const r = await call('GET', '/api/parts?q=a_b');
  assert.equal(r.body.data.length, 0);
});

test('GET /api/parts without q returns at most 50 rows sorted by part_number', async () => {
  for (let i = 0; i < 55; i++) {
    await create({ barcode: `B${i}`, part_number: `P-${String(54 - i).padStart(2, '0')}` });
  }
  const r = await call('GET', '/api/parts');
  assert.equal(r.body.data.length, 50);
  assert.equal(r.body.data[0].part_number, 'P-00');
  assert.equal(r.body.data[49].part_number, 'P-49');
});

test('PUT /api/parts/:id updates fields but not barcode or stock', async () => {
  const { body } = await create();
  const { barcode, ...rest } = samplePart;
  const r = await call('PUT', `/api/parts/${body.data.id}`, { ...rest, description: 'Kampas rem depan', barcode: 'X', stock: 99 });
  assert.equal(r.status, 200);
  assert.equal(r.body.data.description, 'Kampas rem depan');
  assert.equal(r.body.data.barcode, barcode);
  assert.equal(r.body.data.stock, 0);
});

test('PUT /api/parts/9999 returns 404', async () => {
  const { barcode, ...rest } = samplePart;
  const r = await call('PUT', '/api/parts/9999', rest);
  assert.equal(r.status, 404);
});

test('malformed JSON body returns 400 envelope', async () => {
  const r = await call('POST', '/api/parts', 'not json');
  assert.equal(r.status, 400);
  assert.equal(r.body.success, false);
});

test('unknown API path returns 404 envelope', async () => {
  const r = await call('GET', '/api/tidak-ada');
  assert.equal(r.status, 404);
  assert.equal(r.body.success, false);
});

import { openDb } from '../src/db.js';
import { createApp } from '../src/app.js';

export async function startTestServer() {
  const db = openDb(':memory:');
  const app = createApp({ db });
  const server = await new Promise(resolve => {
    const s = app.listen(0, () => resolve(s));
  });
  const baseUrl = `http://127.0.0.1:${server.address().port}`;
  const close = () => new Promise(resolve => server.close(() => { db.close(); resolve(); }));
  return { baseUrl, db, close };
}

export async function request(baseUrl, method, path, body) {
  const init = { method, headers: {} };
  if (body !== undefined) {
    init.headers['Content-Type'] = 'application/json';
    init.body = typeof body === 'string' ? body : JSON.stringify(body);
  }
  const res = await fetch(baseUrl + path, init);
  return { status: res.status, body: await res.json() };
}

export const samplePart = {
  barcode: '0012345678905',
  part_number: 'BP-01',
  name: 'Kampas rem',
  description: 'Untuk Innova Reborn',
  price: 150000,
};

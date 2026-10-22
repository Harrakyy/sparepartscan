const NETWORK_ERROR = 'Tidak bisa terhubung ke server. Pastikan laptop dan tunnel masih berjalan.';
const SERVER_ERROR = 'Terjadi kesalahan di server. Coba lagi.';

async function call(method, path, body) {
  const init = { method, headers: { Accept: 'application/json' } };
  if (body !== undefined) {
    init.headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(body);
  }
  let res;
  try {
    res = await fetch(path, init);
  } catch {
    throw Object.assign(new Error(NETWORK_ERROR), { status: 0 });
  }
  let payload;
  try {
    payload = await res.json();
  } catch {
    throw Object.assign(new Error(SERVER_ERROR), { status: res.status });
  }
  if (!payload.success) throw Object.assign(new Error(payload.error || SERVER_ERROR), { status: res.status });
  return payload.data;
}

export const apiGet = path => call('GET', path);
export const apiPost = (path, body) => call('POST', path, body);
export const apiPut = (path, body) => call('PUT', path, body);

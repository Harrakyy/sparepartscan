const NETWORK_ERROR = 'Tidak bisa terhubung ke server. Periksa koneksi lalu coba lagi.';
const SERVER_ERROR = 'Terjadi kesalahan di server. Coba lagi.';
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function call(method, path, body) {
  const init = { method, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() } };
  if (body instanceof FormData) {
    init.body = body;
  } else if (body !== undefined) {
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
  if (!payload.success) {
    throw Object.assign(new Error(payload.error || payload.message || SERVER_ERROR), { status: res.status, data: payload.data ?? null });
  }
  return payload.data;
}

export const apiGet = path => call('GET', path);
export const apiPost = (path, body) => call('POST', path, body);

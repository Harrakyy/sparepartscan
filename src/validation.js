const MAX_PRICE = 1_000_000_000;
const MAX_QTY = 100_000;

const PRICE_FIELDS = [
  ['price_retail', 'Harga umum'],
  ['price_member', 'Harga langganan'],
  ['price_wholesale', 'Harga grosir'],
];

class ValidationError extends Error {}

function text(body, field, label, { min = 1, max }) {
  const raw = body[field] ?? '';
  if (typeof raw !== 'string') throw new ValidationError(`${label} harus berupa teks`);
  const value = raw.trim();
  if (value.length < min || value.length > max) {
    throw new ValidationError(min > 0
      ? `${label} wajib diisi (maks. ${max} karakter)`
      : `${label} maks. ${max} karakter`);
  }
  return value;
}

function integer(body, field, label, min, max) {
  const value = body[field];
  if (!Number.isInteger(value) || value < min || value > max) {
    throw new ValidationError(`${label} harus bilangan bulat ${min.toLocaleString('id-ID')}–${max.toLocaleString('id-ID')}`);
  }
  return value;
}

function run(body, build) {
  if (!body || typeof body !== 'object' || Array.isArray(body)) {
    return { ok: false, error: 'Data tidak valid' };
  }
  try {
    return { ok: true, value: build(body) };
  } catch (err) {
    if (err instanceof ValidationError) return { ok: false, error: err.message };
    throw err;
  }
}

function partFields(body) {
  const value = {
    part_number: text(body, 'part_number', 'Part number', { max: 64 }),
    description: text(body, 'description', 'Deskripsi', { max: 500 }),
  };
  for (const [field, label] of PRICE_FIELDS) value[field] = integer(body, field, label, 0, MAX_PRICE);
  return value;
}

export function validatePartCreate(body) {
  return run(body, b => ({ barcode: text(b, 'barcode', 'Barcode', { max: 64 }), ...partFields(b) }));
}

export function validatePartUpdate(body) {
  return run(body, partFields);
}

export function validateMovement(body) {
  return run(body, b => {
    if (b.type !== 'in' && b.type !== 'out') throw new ValidationError('Jenis harus masuk atau keluar');
    return {
      part_id: integer(b, 'part_id', 'Part', 1, Number.MAX_SAFE_INTEGER),
      type: b.type,
      qty: integer(b, 'qty', 'Jumlah', 1, MAX_QTY),
      note: text(b, 'note', 'Catatan', { min: 0, max: 200 }),
    };
  });
}

export function parseLimit(raw, def, max) {
  const n = Number.parseInt(raw, 10);
  if (!Number.isInteger(n) || n < 1) return def;
  return Math.min(n, max);
}

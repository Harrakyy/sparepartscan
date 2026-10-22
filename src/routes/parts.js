import { Router } from 'express';
import { createPartsRepo } from '../repositories/parts.js';
import { validatePartCreate, validatePartUpdate } from '../validation.js';
import { ok, fail } from '../respond.js';

const NOT_FOUND = 'Part tidak ditemukan';

export function partsRouter(db) {
  const parts = createPartsRepo(db);
  const router = Router();

  router.get('/by-barcode/:barcode', (req, res) => {
    const part = parts.findByBarcode(req.params.barcode.trim());
    return part ? ok(res, part) : fail(res, 404, 'Barcode belum terdaftar');
  });

  router.get('/', (req, res) => {
    const q = typeof req.query.q === 'string' ? req.query.q.trim().slice(0, 64) : '';
    return ok(res, parts.search(q));
  });

  router.post('/', (req, res) => {
    const v = validatePartCreate(req.body);
    if (!v.ok) return fail(res, 400, v.error);
    try {
      return ok(res, parts.create(v.value), 201);
    } catch (err) {
      if (err.code === 'DUPLICATE_BARCODE') return fail(res, 409, err.message);
      throw err;
    }
  });

  router.put('/:id', (req, res) => {
    const id = Number(req.params.id);
    if (!Number.isInteger(id) || id < 1) return fail(res, 404, NOT_FOUND);
    const v = validatePartUpdate(req.body);
    if (!v.ok) return fail(res, 400, v.error);
    const part = parts.update(id, v.value);
    return part ? ok(res, part) : fail(res, 404, NOT_FOUND);
  });

  return router;
}

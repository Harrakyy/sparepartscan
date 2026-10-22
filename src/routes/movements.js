import { Router } from 'express';
import { createMovementsRepo } from '../repositories/movements.js';
import { validateMovement, parseLimit } from '../validation.js';
import { ok, fail } from '../respond.js';

const DEFAULT_LIMIT = 100;
const MAX_LIMIT = 500;

const ERROR_STATUS = { PART_NOT_FOUND: 404, INSUFFICIENT_STOCK: 409 };

export function movementsRouter(db) {
  const movements = createMovementsRepo(db);
  const router = Router();

  router.get('/', (req, res) => ok(res, movements.recent(parseLimit(req.query.limit, DEFAULT_LIMIT, MAX_LIMIT))));

  router.post('/', (req, res) => {
    const v = validateMovement(req.body);
    if (!v.ok) return fail(res, 400, v.error);
    try {
      return ok(res, movements.record(v.value), 201);
    } catch (err) {
      const status = ERROR_STATUS[err.code];
      if (status) return fail(res, status, err.message);
      throw err;
    }
  });

  return router;
}

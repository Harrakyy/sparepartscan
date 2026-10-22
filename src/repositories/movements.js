function domainError(code, message, extra = {}) {
  return Object.assign(new Error(message), { code, ...extra });
}

export function createMovementsRepo(db) {
  const getPart = db.prepare('SELECT * FROM parts WHERE id = ?');
  const addStock = db.prepare('UPDATE parts SET stock = stock + ?, updated_at = ? WHERE id = ?');
  const takeStock = db.prepare('UPDATE parts SET stock = stock - ?, updated_at = ? WHERE id = ? AND stock >= ?');
  const insert = db.prepare(`
    INSERT INTO movements (part_id, type, qty, stock_after, note, created_at)
    VALUES (?, ?, ?, ?, ?, ?)`);
  const getMovement = db.prepare('SELECT * FROM movements WHERE id = ?');
  const recentStmt = db.prepare(`
    SELECT m.*, p.part_number, p.name, p.description
    FROM movements m JOIN parts p ON p.id = m.part_id
    ORDER BY m.created_at DESC, m.id DESC LIMIT ?`);

  function applyStock({ part_id, type, qty }, now) {
    const { changes } = type === 'in'
      ? addStock.run(qty, now, part_id)
      : takeStock.run(qty, now, part_id, qty);
    if (changes > 0) return getPart.get(part_id);
    const part = getPart.get(part_id);
    if (!part) throw domainError('PART_NOT_FOUND', 'Part tidak ditemukan');
    throw domainError('INSUFFICIENT_STOCK', `Stok tidak cukup (stok: ${part.stock})`, { stock: part.stock });
  }

  return {
    record(input) {
      const now = new Date().toISOString();
      db.exec('BEGIN IMMEDIATE');
      try {
        const part = applyStock(input, now);
        const { lastInsertRowid } = insert.run(input.part_id, input.type, input.qty, part.stock, input.note, now);
        db.exec('COMMIT');
        return { movement: getMovement.get(lastInsertRowid), part };
      } catch (err) {
        db.exec('ROLLBACK');
        throw err;
      }
    },

    recent: limit => recentStmt.all(limit),
  };
}

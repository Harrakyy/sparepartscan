const SEARCH_LIMIT = 50;

function escapeLike(q) {
  return q.replace(/[\\%_]/g, '\\$&');
}

export function createPartsRepo(db) {
  const byBarcode = db.prepare('SELECT * FROM parts WHERE barcode = ?');
  const byId = db.prepare('SELECT * FROM parts WHERE id = ?');
  const searchStmt = db.prepare(`
    SELECT * FROM parts
    WHERE barcode LIKE ? ESCAPE '\\' OR part_number LIKE ? ESCAPE '\\' OR name LIKE ? ESCAPE '\\' OR description LIKE ? ESCAPE '\\'
    ORDER BY part_number LIMIT ?`);
  const insert = db.prepare(`
    INSERT INTO parts (barcode, part_number, name, description, price, stock, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, 0, ?, ?)`);
  const updateStmt = db.prepare(`
    UPDATE parts SET part_number = ?, name = ?, description = ?, price = ?, updated_at = ?
    WHERE id = ?`);

  return {
    findByBarcode: barcode => byBarcode.get(barcode),
    findById: id => byId.get(id),

    search(q, limit = SEARCH_LIMIT) {
      const pattern = `%${escapeLike(q)}%`;
      return searchStmt.all(pattern, pattern, pattern, pattern, limit);
    },

    create(input) {
      const now = new Date().toISOString();
      try {
        const { lastInsertRowid } = insert.run(
          input.barcode, input.part_number, input.name, input.description, input.price, now, now,
        );
        return byId.get(lastInsertRowid);
      } catch (err) {
        if (/UNIQUE constraint failed: parts\.barcode/.test(err.message)) {
          throw Object.assign(new Error('Barcode sudah terdaftar'), { code: 'DUPLICATE_BARCODE' });
        }
        throw err;
      }
    },

    update(id, input) {
      const { changes } = updateStmt.run(
        input.part_number, input.name, input.description, input.price,
        new Date().toISOString(), id,
      );
      return changes === 0 ? undefined : byId.get(id);
    },
  };
}

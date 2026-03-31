import { query, transaction } from '../../lib/db.js';

export const prerender = false;

function jsonResponse(obj, status = 200) {
  return new Response(JSON.stringify(obj), { status, headers: { 'Content-Type': 'application/json' } });
}

export async function POST({ request }) {
  try {
    const body = await request.json();
    if (!body || !body.name) return jsonResponse({ error: 'Missing required field: name' }, 400);

    const slug = String(body.name).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

    const insertFields = [
      'external_id', 'name', 'slug', 'description', 'client', 'owner_user_id', 'status', 'budget_amount', 'currency', 'start_date', 'end_date', 'metadata', 'is_active'
    ];

    const values = insertFields.map(f => body[f] ?? null);
    if (body.metadata && typeof body.metadata !== 'string') values[10] = JSON.stringify(body.metadata);

    const placeholders = insertFields.map(() => '?').join(',');
    const sql = `INSERT INTO projects (${insertFields.join(',')}) VALUES (${placeholders})`;

    const result = await transaction(async (conn) => {
      const [res] = await conn.query(sql, values);
      const insertedId = res.insertId;
      const [rows] = await conn.query('SELECT * FROM projects WHERE id = ?', [insertedId]);
      return rows?.[0] ?? null;
    });

    return jsonResponse({ data: result }, 201);
  } catch (err) {
    return jsonResponse({ error: err.message }, 500);
  }
}

export async function PUT({ request }) {
  try {
    const body = await request.json();
    const id = body?.id;
    if (!id) return jsonResponse({ error: 'Missing project id' }, 400);

    const allowed = ['external_id', 'name', 'description', 'client', 'owner_user_id', 'status', 'budget_amount', 'currency', 'start_date', 'end_date', 'metadata', 'is_active'];
    const sets = [];
    const params = [];
    for (const key of allowed) {
      if (Object.prototype.hasOwnProperty.call(body, key)) {
        if (key === 'metadata' && typeof body[key] !== 'string') {
          sets.push(`${key} = ?`);
          params.push(JSON.stringify(body[key]));
        } else {
          sets.push(`${key} = ?`);
          params.push(body[key]);
        }
      }
    }
    if (sets.length === 0) return jsonResponse({ error: 'No updatable fields provided' }, 400);

    params.push(id);
    const sql = `UPDATE projects SET ${sets.join(',')}, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL`;
    await query(sql, params);
    const rows = await query('SELECT * FROM projects WHERE id = ?', [id]);
    return jsonResponse({ data: rows?.[0] ?? null }, 200);
  } catch (err) {
    return jsonResponse({ error: err.message }, 500);
  }
}

export async function DELETE({ request }) {
  try {
    const url = new URL(request.url);
    const idFromQuery = url.searchParams.get('id');
    let id = idFromQuery;
    try {
      const body = await request.json().catch(() => null);
      if (body && body.id) id = body.id;
    } catch (e) {
      // ignore
    }
    if (!id) return jsonResponse({ error: 'Missing project id' }, 400);

    await query('UPDATE projects SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL', [id]);
    return jsonResponse({ data: { id, deleted: true } }, 200);
  } catch (err) {
    return jsonResponse({ error: err.message }, 500);
  }
}

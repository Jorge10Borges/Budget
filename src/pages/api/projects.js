import { query } from '../../lib/db.js';

export const prerender = false;

export async function GET({ request }) {
  const apiToken = process.env.API_TOKEN;
  if (!apiToken) {
    return new Response(JSON.stringify({ error: 'API_TOKEN not configured on server' }), { status: 500, headers: { 'Content-Type': 'application/json' } });
  }

  const header = request.headers.get('x-api-token');
  const url = new URL(request.url);
  const tokenParam = url.searchParams.get('token');
  const provided = header || tokenParam;

  if (!provided || provided !== apiToken) {
    return new Response(JSON.stringify({ error: 'Unauthorized' }), { status: 401, headers: { 'Content-Type': 'application/json' } });
  }

  // Pagination
  const page = Math.max(1, Number(url.searchParams.get('page')) || 1);
  const per_page = Math.min(100, Math.max(1, Number(url.searchParams.get('per_page')) || 20));
  const offset = (page - 1) * per_page;

  // Filters
  const status = url.searchParams.get('status');
  const client = url.searchParams.get('client');

  // Sorting
  const sort = url.searchParams.get('sort') || 'start_date';
  const dir = (url.searchParams.get('dir') || 'desc').toUpperCase() === 'ASC' ? 'ASC' : 'DESC';
  const allowedSort = ['start_date', 'created_at', 'updated_at'];
  const orderBy = allowedSort.includes(sort) ? sort : 'start_date';

  // Build SQL
  const where = ['deleted_at IS NULL'];
  const params = [];
  if (status) {
    where.push('status = ?');
    params.push(status);
  }
  if (client) {
    where.push('client LIKE ?');
    params.push(`%${client}%`);
  }

  const whereClause = where.length ? `WHERE ${where.join(' AND ')}` : '';

  try {
    // total count
    const countSql = `SELECT COUNT(*) AS total FROM projects ${whereClause}`;
    const countRows = await query(countSql, params);
    const total = countRows?.[0]?.total || 0;

    const sql = `SELECT id, external_id, name, slug, description, client, owner_user_id, status, budget_amount, currency, start_date, end_date, metadata, is_active, created_at, updated_at FROM projects ${whereClause} ORDER BY ${orderBy} ${dir} LIMIT ? OFFSET ?`;
    const dataParams = params.slice();
    dataParams.push(per_page, offset);

    const rows = await query(sql, dataParams);

    // parse metadata JSON if not already parsed
    const parsed = rows.map(r => ({ ...r, metadata: r.metadata ? r.metadata : null }));

    const meta = { total, page, per_page };
    return new Response(JSON.stringify({ data: parsed, meta }), { status: 200, headers: { 'Content-Type': 'application/json' } });
  } catch (err) {
    return new Response(JSON.stringify({ error: err.message }), { status: 500, headers: { 'Content-Type': 'application/json' } });
  }
}

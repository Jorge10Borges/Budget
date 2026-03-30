import { query } from '../../lib/db.js';

// Ensure this route is server-rendered so request headers and URL are available
export const prerender = false;

// DEBUG: show whether API_TOKEN is loaded (remove in production)
export async function GET({ request }) {
  const apiToken = process.env.API_TOKEN;

  // Exigir API_TOKEN: si no está definido en el entorno, rechazar para evitar endpoints públicos
  if (!apiToken) {
    return new Response(JSON.stringify({ error: 'API_TOKEN not configured on server' }), { status: 500, headers: { 'Content-Type': 'application/json' } });
  }

    // Prefer header, but allow token via query param for environments where custom headers are stripped
    const header = request.headers.get('x-api-token');
    const url = new URL(request.url);
    const tokenParam = url.searchParams.get('token');
    const provided = header || tokenParam;

  if (!provided || provided !== apiToken) {
    return new Response(JSON.stringify({ error: 'Unauthorized' }), { status: 401, headers: { 'Content-Type': 'application/json' } });
  }

  try {
    const rows = await query('SELECT 1 AS ok');
    return new Response(JSON.stringify({ ok: true, result: rows }), { status: 200, headers: { 'Content-Type': 'application/json' } });
  } catch (err) {
    return new Response(JSON.stringify({ ok: false, error: err.message }), { status: 500, headers: { 'Content-Type': 'application/json' } });
  }
}

import { query } from '../src/lib/db.js';

async function run() {
  try {
    const rows = await query('SELECT id, external_id, name, status, created_at FROM projects ORDER BY id DESC LIMIT 5');
    console.table(rows);
    process.exit(0);
  } catch (err) {
    console.error('Error querying projects:', err);
    process.exit(1);
  }
}

run();

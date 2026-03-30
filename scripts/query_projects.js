import { query } from '../src/lib/db.js';
import dotenv from 'dotenv';
import { resolve } from 'path';

dotenv.config({ path: resolve(process.cwd(), '.env') });

async function run() {
  try {
    const rows = await query('SELECT id, external_id, name, client, status, budget_amount, start_date, end_date, metadata FROM projects WHERE deleted_at IS NULL ORDER BY start_date DESC LIMIT 100');
    console.log('Proyectos encontrados:', rows.length);
    console.table(rows.map(r => ({ id: r.id, external_id: r.external_id, name: r.name, client: r.client, status: r.status, budget: r.budget_amount })));
  } catch (err) {
    console.error('Error al leer proyectos:', err.message || err);
    process.exitCode = 1;
  }
}

run();

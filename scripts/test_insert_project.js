import { transaction } from '../src/lib/db.js';

async function run() {
  try {
    const now = Date.now();
    const externalId = 'TEST-' + now;
    const name = 'Proyecto de prueba ' + now;
    const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    const [result] = await transaction(async (conn) => {
      const [res] = await conn.query(`INSERT INTO projects (external_id, name, slug, description, client, status, budget_amount, currency, is_active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())`, [externalId, name, slug, 'Insertado por test_insert_project.js', 'Cliente Test', 'draft', 1000.00, 'USD', 1]);
      return [res];
    });
    console.log('Inserted project id:', result.insertId);
    process.exit(0);
  } catch (err) {
    console.error('Error inserting test project:', err);
    process.exit(1);
  }
}

run();

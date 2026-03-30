import { query, closePool } from '../src/lib/db.js';

async function main() {
  try {
    const rows = await query('SELECT 1 AS ok');
    console.log('DB test result:', rows);
  } catch (err) {
    console.error('DB connection test failed:', err && err.message ? err.message : err);
    process.exitCode = 1;
  } finally {
    try {
      await closePool();
    } catch {}
  }
}

main();

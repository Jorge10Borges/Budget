import fs from 'fs';
import path from 'path';
import dotenv from 'dotenv';
import mysql from 'mysql2/promise';

dotenv.config({ path: path.resolve(process.cwd(), '.env') });

const {
  DB_HOST, DB_USER, DB_PASSWORD, DB_NAME
} = process.env;

if (!DB_HOST || !DB_USER || !DB_PASSWORD || !DB_NAME) {
  console.error('Faltan variables de entorno DB_HOST/DB_USER/DB_PASSWORD/DB_NAME en .env');
  process.exit(2);
}

// Allow passing a SQL file path as first CLI argument, otherwise default to create_projects_table.sql
const argPath = process.argv[2];
const sqlPath = argPath
  ? path.resolve(process.cwd(), argPath)
  : path.resolve(process.cwd(), 'scripts', 'create_projects_table.sql');

if (!fs.existsSync(sqlPath)) {
  console.error('No se encontró', sqlPath);
  process.exit(3);
}

const sql = fs.readFileSync(sqlPath, 'utf8');

async function run() {
  let conn;
  try {
    conn = await mysql.createConnection({
      host: DB_HOST,
      user: DB_USER,
      password: DB_PASSWORD,
      database: DB_NAME,
      charset: 'utf8mb4',
      multipleStatements: true
    });

    console.log('Conectado a', DB_HOST, 'base', DB_NAME);
    const [result] = await conn.query(sql);
    console.log('Resultado:', result);
    console.log('Script ejecutado correctamente.');
  } catch (err) {
    console.error('Error al ejecutar SQL:', err.message || err);
    process.exitCode = 1;
  } finally {
    if (conn && conn.end) await conn.end();
  }
}

run();

import mysql from 'mysql2/promise';
import dotenv from 'dotenv';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';

// Cargar `.env` explícitamente desde la raíz del proyecto (más robusto bajo Vite)
try {
  const __filename = fileURLToPath(import.meta.url);
  const __dirname = dirname(__filename);
  dotenv.config({ path: resolve(__dirname, '../../.env') });
} catch (err) {
  // Si falla, intentar carga por defecto
  dotenv.config();
}

const {
  DB_HOST,
  DB_PORT = '3306',
  DB_USER,
  DB_PASSWORD,
  DB_NAME,
} = process.env;

const missing = [];
if (!DB_HOST) missing.push('DB_HOST');
if (!DB_USER) missing.push('DB_USER');
if (!DB_PASSWORD) missing.push('DB_PASSWORD');
if (!DB_NAME) missing.push('DB_NAME');

if (missing.length) {
  throw new Error(
    `Missing DB env vars: ${missing.join(', ')}. Run with 'node -r dotenv/config' or set env vars.`
  );
}

const pool = mysql.createPool({
  host: DB_HOST,
  port: Number(DB_PORT) || 3306,
  user: DB_USER,
  password: DB_PASSWORD,
  database: DB_NAME,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
});

export const dbPool = pool;

export async function query(sql, params = []) {
  if (!sql) throw new Error('SQL is required');
  try {
    const [rows] = await pool.query(sql, params);
    return rows;
  } catch (err) {
    const e = new Error(`DB query error: ${err.message}`);
    e.cause = err;
    throw e;
  }
}

export async function getConnection() {
  try {
    const conn = await pool.getConnection();
    return conn;
  } catch (err) {
    const e = new Error(`Failed to get DB connection: ${err.message}`);
    e.cause = err;
    throw e;
  }
}

export async function transaction(fn) {
  if (typeof fn !== 'function') throw new Error('transaction expects a callback');
  const conn = await getConnection();
  let committed = false;
  try {
    await conn.beginTransaction();
    const res = await fn(conn);
    await conn.commit();
    committed = true;
    return res;
  } catch (err) {
    try {
      if (!committed) await conn.rollback();
    } catch (rbErr) {
      console.error('Rollback error:', rbErr);
    }
    throw err;
  } finally {
    try {
      conn.release();
    } catch (releaseErr) {
      console.error('Release error:', releaseErr);
    }
  }
}

export async function closePool() {
  try {
    await pool.end();
  } catch (err) {
    const e = new Error(`Failed to close DB pool: ${err.message}`);
    e.cause = err;
    throw e;
  }
}

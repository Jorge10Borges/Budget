-- Example seed for first tenant (run after schema migration)
-- Replace placeholders before executing.

-- 1) Create company
INSERT INTO companies (legal_name, trade_name, tax_id, status)
VALUES ('ACME Construcciones C.A.', 'ACME', 'J-00000000-0', 'active');

-- 2) Create license for company #1
INSERT INTO licenses (company_id, license_key, plan_name, max_users, starts_at, ends_at, status)
VALUES (1, 'LIC-ACME-2026-0001', 'standard', 10, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active');

-- 3) Create first admin user for company #1
-- Generate hash with PHP: password_hash('TuPasswordSeguro', PASSWORD_DEFAULT)
INSERT INTO users (company_id, full_name, email, password_hash, role, is_active)
VALUES (1, 'Administrador ACME', 'admin@acme.local', '$2y$10$REEMPLAZAR_HASH_GENERADO', 'admin', 1);

-- 4) Assign existing data to company #1 (one-time migration)
UPDATE projects SET company_id = 1 WHERE company_id IS NULL;
UPDATE items SET company_id = 1 WHERE company_id IS NULL;
UPDATE employees SET company_id = 1 WHERE company_id IS NULL;

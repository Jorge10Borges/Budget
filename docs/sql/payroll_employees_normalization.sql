-- Normalizacion de nomina:
-- 1) empleados globales (sin project_id)
-- 2) relacion empleado-proyecto en project_employees
-- 3) payroll_entries ligado a project_id + employee_id
--
-- Requisitos de negocio aplicados:
-- - Empleado puede estar en multiples proyectos
-- - Datos de cuadrilla/tarifas/globales en employees
-- - Unicidad por (full_name, id_number)
-- - Compatibilidad de API mantenida en capa de endpoints

START TRANSACTION;

-- 1) Renombrar tabla legacy y crear nueva tabla employees (global)
RENAME TABLE employees TO employees_legacy;

CREATE TABLE IF NOT EXISTS employees (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(191) NOT NULL,
  id_number VARCHAR(50) NULL,
  mobile_bank VARCHAR(120) NULL,
  mobile_id_number VARCHAR(50) NULL,
  mobile_phone VARCHAR(50) NULL,
  bank_account_number VARCHAR(60) NULL,
  bank_account_holder_name VARCHAR(191) NULL,
  bank_account_holder_id VARCHAR(50) NULL,
  crew ENUM('day','night') NOT NULL DEFAULT 'day',
  day_rate DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  night_rate DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_employees_full_name_id_number (full_name, id_number),
  KEY idx_employees_id_number (id_number),
  KEY idx_employees_full_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Tabla relacion empleado-proyecto
CREATE TABLE IF NOT EXISTS project_employees (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_project_employees_project_employee (project_id, employee_id),
  KEY idx_project_employees_project (project_id),
  KEY idx_project_employees_employee (employee_id),
  KEY idx_project_employees_is_active (is_active),
  CONSTRAINT fk_project_employees_project
    FOREIGN KEY (project_id) REFERENCES projects (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_project_employees_employee
    FOREIGN KEY (employee_id) REFERENCES employees (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Migrar empleados globales deduplicando por (full_name, id_number)
INSERT INTO employees (
  full_name,
  id_number,
  mobile_bank,
  mobile_id_number,
  mobile_phone,
  bank_account_number,
  bank_account_holder_name,
  bank_account_holder_id,
  crew,
  day_rate,
  night_rate,
  created_at,
  updated_at
)
SELECT
  el.full_name,
  el.id_number,
  el.mobile_bank,
  el.mobile_id_number,
  el.mobile_phone,
  el.bank_account_number,
  el.bank_account_holder_name,
  el.bank_account_holder_id,
  el.crew,
  el.day_rate,
  el.night_rate,
  MIN(el.created_at) AS created_at,
  MAX(el.updated_at) AS updated_at
FROM employees_legacy el
GROUP BY
  el.full_name,
  el.id_number,
  el.mobile_bank,
  el.mobile_id_number,
  el.mobile_phone,
  el.bank_account_number,
  el.bank_account_holder_name,
  el.bank_account_holder_id,
  el.crew,
  el.day_rate,
  el.night_rate;

-- 4) Migrar relacion proyecto-empleado con estado activo por proyecto
INSERT INTO project_employees (project_id, employee_id, is_active, created_at, updated_at)
SELECT
  el.project_id,
  e.id AS employee_id,
  MAX(el.is_active) AS is_active,
  MIN(el.created_at) AS created_at,
  MAX(el.updated_at) AS updated_at
FROM employees_legacy el
INNER JOIN employees e
  ON e.full_name = el.full_name
 AND ((e.id_number IS NULL AND el.id_number IS NULL) OR e.id_number = el.id_number)
 AND e.crew = el.crew
 AND e.day_rate = el.day_rate
 AND e.night_rate = el.night_rate
GROUP BY el.project_id, e.id;

-- 5) Ajustar payroll_entries para guardar project_id
ALTER TABLE payroll_entries
  ADD COLUMN project_id INT UNSIGNED NULL AFTER employee_id;

UPDATE payroll_entries pe
INNER JOIN employees_legacy el ON el.id = pe.employee_id
SET pe.project_id = el.project_id
WHERE pe.project_id IS NULL;

ALTER TABLE payroll_entries
  MODIFY COLUMN project_id INT UNSIGNED NOT NULL;

-- Re-crear claves/indices para nuevo modelo
ALTER TABLE payroll_entries
  DROP FOREIGN KEY fk_payroll_entries_employee;

ALTER TABLE payroll_entries
  DROP INDEX uq_payroll_entries_employee_date;

ALTER TABLE payroll_entries
  ADD CONSTRAINT fk_payroll_entries_employee
    FOREIGN KEY (employee_id) REFERENCES employees (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_payroll_entries_project
    FOREIGN KEY (project_id) REFERENCES projects (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD UNIQUE KEY uq_payroll_entries_project_employee_date (project_id, employee_id, work_date),
  ADD KEY idx_payroll_entries_project_id (project_id);

-- Limpieza: eliminar tabla legacy si todo fue correcto
DROP TABLE employees_legacy;

COMMIT;

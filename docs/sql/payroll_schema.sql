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

CREATE TABLE IF NOT EXISTS project_employees (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_project_employees_project_employee (project_id, employee_id),
  KEY idx_project_employees_project_id (project_id),
  KEY idx_project_employees_employee_id (employee_id),
  KEY idx_project_employees_is_active (is_active),
  CONSTRAINT fk_project_employees_project
    FOREIGN KEY (project_id) REFERENCES projects (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_project_employees_employee
    FOREIGN KEY (employee_id) REFERENCES employees (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_entries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  work_date DATE NOT NULL,
  worked_day DECIMAL(3,1) NOT NULL DEFAULT 0.0,
  worked_night DECIMAL(3,1) NOT NULL DEFAULT 0.0,
  paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payroll_entries_project_employee_date (project_id, employee_id, work_date),
  KEY idx_payroll_entries_project_id (project_id),
  KEY idx_payroll_entries_work_date (work_date),
  CONSTRAINT fk_payroll_entries_project
    FOREIGN KEY (project_id) REFERENCES projects (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_payroll_entries_employee
    FOREIGN KEY (employee_id) REFERENCES employees (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

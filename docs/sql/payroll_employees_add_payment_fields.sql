SET @db_name = DATABASE();

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db_name
        AND TABLE_NAME = 'employees'
        AND COLUMN_NAME = 'id_number'
    ),
    'SELECT "id_number ya existe"',
    'ALTER TABLE employees ADD COLUMN id_number VARCHAR(50) NULL AFTER full_name'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db_name
        AND TABLE_NAME = 'employees'
        AND COLUMN_NAME = 'mobile_bank'
    ),
    'SELECT "mobile_bank ya existe"',
    'ALTER TABLE employees ADD COLUMN mobile_bank VARCHAR(120) NULL AFTER id_number'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db_name
        AND TABLE_NAME = 'employees'
        AND COLUMN_NAME = 'mobile_id_number'
    ),
    'SELECT "mobile_id_number ya existe"',
    'ALTER TABLE employees ADD COLUMN mobile_id_number VARCHAR(50) NULL AFTER mobile_bank'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db_name
        AND TABLE_NAME = 'employees'
        AND COLUMN_NAME = 'mobile_phone'
    ),
    'SELECT "mobile_phone ya existe"',
    'ALTER TABLE employees ADD COLUMN mobile_phone VARCHAR(50) NULL AFTER mobile_id_number'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db_name
        AND TABLE_NAME = 'employees'
        AND COLUMN_NAME = 'bank_account_number'
    ),
    'SELECT "bank_account_number ya existe"',
    'ALTER TABLE employees ADD COLUMN bank_account_number VARCHAR(60) NULL AFTER mobile_phone'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db_name
        AND TABLE_NAME = 'employees'
        AND COLUMN_NAME = 'bank_account_holder_name'
    ),
    'SELECT "bank_account_holder_name ya existe"',
    'ALTER TABLE employees ADD COLUMN bank_account_holder_name VARCHAR(191) NULL AFTER bank_account_number'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db_name
        AND TABLE_NAME = 'employees'
        AND COLUMN_NAME = 'bank_account_holder_id'
    ),
    'SELECT "bank_account_holder_id ya existe"',
    'ALTER TABLE employees ADD COLUMN bank_account_holder_id VARCHAR(50) NULL AFTER bank_account_holder_name'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

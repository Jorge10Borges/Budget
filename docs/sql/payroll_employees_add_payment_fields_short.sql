ALTER TABLE employees
  ADD COLUMN id_number VARCHAR(50) NULL AFTER full_name,
  ADD COLUMN mobile_bank VARCHAR(120) NULL AFTER id_number,
  ADD COLUMN mobile_id_number VARCHAR(50) NULL AFTER mobile_bank,
  ADD COLUMN mobile_phone VARCHAR(50) NULL AFTER mobile_id_number,
  ADD COLUMN bank_account_number VARCHAR(60) NULL AFTER mobile_phone,
  ADD COLUMN bank_account_holder_name VARCHAR(191) NULL AFTER bank_account_number,
  ADD COLUMN bank_account_holder_id VARCHAR(50) NULL AFTER bank_account_holder_name;

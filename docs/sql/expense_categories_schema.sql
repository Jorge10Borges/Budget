CREATE TABLE IF NOT EXISTS expense_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_expense_categories_code (code),
  KEY idx_expense_categories_is_active (is_active),
  KEY idx_expense_categories_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE expenses
  ADD COLUMN IF NOT EXISTS category_id INT UNSIGNED NULL AFTER project_item_id,
  ADD KEY idx_expenses_category_id (category_id),
  ADD CONSTRAINT fk_expenses_category
    FOREIGN KEY (category_id) REFERENCES expense_categories (id)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- Crear tabla projects para MySQL (utf8mb4, InnoDB)
CREATE TABLE IF NOT EXISTS `projects` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(100) DEFAULT NULL COMMENT 'ID externo/código del proyecto',
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) GENERATED ALWAYS AS (LOWER(REPLACE(name,' ','-'))) VIRTUAL,
  `description` TEXT DEFAULT NULL,
  `client` VARCHAR(255) DEFAULT NULL,
  `owner_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `status` ENUM('draft','active','paused','completed','cancelled') NOT NULL DEFAULT 'draft',
  `budget_amount` DECIMAL(15,2) DEFAULT 0.00,
  `currency` CHAR(3) DEFAULT 'USD',
  `start_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_projects_external_id` (`external_id`),
  INDEX `ix_projects_owner` (`owner_user_id`),
  INDEX `ix_projects_status` (`status`),
  INDEX `ix_projects_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fin

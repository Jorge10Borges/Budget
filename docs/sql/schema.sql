CREATE DATABASE IF NOT EXISTS `budget`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `budget`;

CREATE TABLE IF NOT EXISTS `projects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(100) NULL,
  `name` VARCHAR(191) NOT NULL,
  `description` TEXT NULL,
  `client` VARCHAR(191) NULL,
  `owner_user_id` INT UNSIGNED NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `last_activity` DATETIME NULL,
  `collected` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `spent` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `metadata` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_projects_status` (`status`),
  KEY `idx_projects_is_active` (`is_active`),
  KEY `idx_projects_deleted_at` (`deleted_at`),
  KEY `idx_projects_start_date` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL,
  `unit` VARCHAR(30) NULL,
  `unit_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_items_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `qty` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `unit_cost` DECIMAL(14,2) NULL,
  `total_cost` DECIMAL(14,2) NULL,
  `status` VARCHAR(30) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project_items_project_id` (`project_id`),
  KEY `idx_project_items_item_id` (`item_id`),
  KEY `idx_project_items_status` (`status`),
  CONSTRAINT `fk_project_items_project`
    FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_project_items_item`
    FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `valuations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
  `created_by` INT UNSIGNED NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_valuations_project_id` (`project_id`),
  KEY `idx_valuations_date` (`date`),
  KEY `idx_valuations_status` (`status`),
  CONSTRAINT `fk_valuations_project`
    FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `valuation_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `valuation_id` INT UNSIGNED NOT NULL,
  `project_item_id` INT UNSIGNED NOT NULL,
  `qty` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `unit_cost` DECIMAL(14,2) NULL,
  `total_cost` DECIMAL(14,2) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_valuation_items_valuation_project_item` (`valuation_id`, `project_item_id`),
  KEY `idx_valuation_items_project_item_id` (`project_item_id`),
  CONSTRAINT `fk_valuation_items_valuation`
    FOREIGN KEY (`valuation_id`) REFERENCES `valuations` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_valuation_items_project_item`
    FOREIGN KEY (`project_item_id`) REFERENCES `project_items` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `project_item_id` INT UNSIGNED NULL,
  `valuation_id` INT UNSIGNED NULL,
  `expense_date` DATE NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
  `vendor` VARCHAR(191) NULL,
  `reference` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expenses_project_id` (`project_id`),
  KEY `idx_expenses_project_item_id` (`project_item_id`),
  KEY `idx_expenses_valuation_id` (`valuation_id`),
  KEY `idx_expenses_status` (`status`),
  KEY `idx_expenses_expense_date` (`expense_date`),
  CONSTRAINT `fk_expenses_project`
    FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_project_item`
    FOREIGN KEY (`project_item_id`) REFERENCES `project_items` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_valuation`
    FOREIGN KEY (`valuation_id`) REFERENCES `valuations` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

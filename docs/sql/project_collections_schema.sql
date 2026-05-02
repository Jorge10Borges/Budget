CREATE TABLE IF NOT EXISTS project_collections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  collection_date DATE NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  collection_kind ENUM('anticipo','otro') NOT NULL,
  other_type VARCHAR(120) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_project_collections_project_date (project_id, collection_date),
  KEY idx_project_collections_kind (collection_kind),
  CONSTRAINT fk_project_collections_project
    FOREIGN KEY (project_id) REFERENCES projects (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

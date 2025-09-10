CREATE TABLE IF NOT EXISTS measurement_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  operation_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  analyzed_at TIMESTAMP NULL,
  notes TEXT NULL,
  CONSTRAINT fk_mf_operation FOREIGN KEY (operation_id) REFERENCES operations(id) ON DELETE CASCADE,
  INDEX idx_mf_operation (operation_id),
  INDEX idx_mf_analyzed (analyzed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

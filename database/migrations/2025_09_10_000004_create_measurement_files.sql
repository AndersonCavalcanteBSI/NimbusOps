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

CREATE TABLE IF NOT EXISTS measurement_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  operation_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  uploaded_by INT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  analyzed_at TIMESTAMP NULL,
  CONSTRAINT fk_mf_op FOREIGN KEY (operation_id) REFERENCES operations(id) ON DELETE CASCADE,
  CONSTRAINT fk_mf_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_mf_op (operation_id),
  INDEX idx_mf_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

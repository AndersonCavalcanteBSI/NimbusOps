CREATE TABLE IF NOT EXISTS measurement_file_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  measurement_file_id INT NOT NULL,
  action VARCHAR(60) NOT NULL,  -- uploaded|analyzed|comment|replaced|...
  notes TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mfh_file FOREIGN KEY (measurement_file_id) REFERENCES measurement_files(id) ON DELETE CASCADE,
  INDEX idx_mfh_file (measurement_file_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

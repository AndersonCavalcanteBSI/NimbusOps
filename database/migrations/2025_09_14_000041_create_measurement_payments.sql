CREATE TABLE IF NOT EXISTS measurement_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  operation_id INT NOT NULL,
  measurement_file_id INT NOT NULL,
  pay_date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  method VARCHAR(60) NULL,
  notes TEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mp_operation   FOREIGN KEY (operation_id)      REFERENCES operations(id)          ON DELETE CASCADE,
  CONSTRAINT fk_mp_file        FOREIGN KEY (measurement_file_id) REFERENCES measurement_files(id) ON DELETE CASCADE,
  CONSTRAINT fk_mp_created_by  FOREIGN KEY (created_by)        REFERENCES users(id)               ON DELETE SET NULL,
  INDEX idx_mp_measurement (measurement_file_id),
  INDEX idx_mp_operation (operation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

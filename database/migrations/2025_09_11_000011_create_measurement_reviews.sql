CREATE TABLE IF NOT EXISTS measurement_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  measurement_file_id INT NOT NULL,
  stage TINYINT NOT NULL, -- 1,2,3 (usamos 1 agora)
  reviewer_user_id INT NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mr_file FOREIGN KEY (measurement_file_id) REFERENCES measurement_files(id) ON DELETE CASCADE,
  CONSTRAINT fk_mr_user FOREIGN KEY (reviewer_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_mr_file_stage (measurement_file_id, stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

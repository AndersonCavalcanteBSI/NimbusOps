CREATE TABLE IF NOT EXISTS operations (
id INT AUTO_INCREMENT PRIMARY KEY,
code VARCHAR(40) NOT NULL UNIQUE,
title VARCHAR(160) NOT NULL,
status ENUM('draft','active','settled','canceled') NOT NULL DEFAULT 'draft',
issuer VARCHAR(160) NULL,
due_date DATE NULL,
amount DECIMAL(18,2) NULL,
created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
INDEX idx_status (status),
INDEX idx_due_date (due_date),
INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE operations
  MODIFY COLUMN status ENUM('draft','active','settled','canceled','pending') NOT NULL DEFAULT 'draft',
  ADD COLUMN responsible_user_id INT NULL AFTER amount,
  ADD CONSTRAINT fk_ops_responsible FOREIGN KEY (responsible_user_id) REFERENCES users(id) ON DELETE SET NULL;

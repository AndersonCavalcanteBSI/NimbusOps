ALTER TABLE operations
  MODIFY COLUMN status ENUM('draft','active','settled','canceled','pending','rejected') NOT NULL DEFAULT 'draft',
  ADD COLUMN rejection_notify_user_id INT NULL AFTER responsible_user_id,
  ADD CONSTRAINT fk_ops_rejection_notify FOREIGN KEY (rejection_notify_user_id) REFERENCES users(id) ON DELETE SET NULL;

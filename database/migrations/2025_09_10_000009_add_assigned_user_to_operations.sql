ALTER TABLE operations
  ADD COLUMN assigned_user_id INT NULL AFTER status,
  ADD CONSTRAINT fk_ops_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL;

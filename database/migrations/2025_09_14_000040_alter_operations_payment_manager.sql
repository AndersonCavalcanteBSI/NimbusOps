ALTER TABLE operations
  ADD COLUMN payment_manager_user_id INT NULL AFTER stage3_reviewer_user_id,
  ADD CONSTRAINT fk_ops_payment_manager
    FOREIGN KEY (payment_manager_user_id) REFERENCES users(id) ON DELETE SET NULL;

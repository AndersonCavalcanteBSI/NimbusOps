ALTER TABLE operations
  ADD COLUMN payment_finalizer_user_id INT NULL AFTER payment_manager_user_id,
  ADD CONSTRAINT fk_ops_payment_finalizer
    FOREIGN KEY (payment_finalizer_user_id) REFERENCES users(id) ON DELETE SET NULL;

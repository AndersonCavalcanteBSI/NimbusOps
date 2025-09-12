ALTER TABLE operations
  ADD COLUMN responsible_user_id INT NULL AFTER amount,
  ADD COLUMN stage2_reviewer_user_id INT NULL AFTER responsible_user_id,
  ADD COLUMN stage3_reviewer_user_id INT NULL AFTER stage2_reviewer_user_id,
  ADD COLUMN payment_manager_user_id INT NULL AFTER stage3_reviewer_user_id,
  ADD COLUMN payment_finalizer_user_id INT NULL AFTER payment_manager_user_id,
  ADD COLUMN rejection_notify_user_id INT NULL AFTER payment_finalizer_user_id,
  ADD CONSTRAINT  fk_ops_resp     FOREIGN KEY (responsible_user_id)        REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_ops_stage2    FOREIGN KEY (stage2_reviewer_user_id)    REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_ops_stage3    FOREIGN KEY (stage3_reviewer_user_id)    REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_ops_paymgr    FOREIGN KEY (payment_manager_user_id)    REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_ops_payfinal  FOREIGN KEY (payment_finalizer_user_id)  REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_ops_reject    FOREIGN KEY (rejection_notify_user_id)   REFERENCES users(id) ON DELETE SET NULL;
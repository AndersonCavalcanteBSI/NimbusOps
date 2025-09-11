ALTER TABLE operations
  ADD COLUMN stage2_reviewer_user_id INT NULL AFTER responsible_user_id,
  ADD CONSTRAINT fk_ops_stage2_reviewer
    FOREIGN KEY (stage2_reviewer_user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE operations
  ADD COLUMN stage3_reviewer_user_id INT NULL AFTER stage2_reviewer_user_id,
  ADD CONSTRAINT fk_ops_stage3_reviewer
    FOREIGN KEY (stage3_reviewer_user_id) REFERENCES users(id) ON DELETE SET NULL;

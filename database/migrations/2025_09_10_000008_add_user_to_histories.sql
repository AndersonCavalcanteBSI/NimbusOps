ALTER TABLE operation_history
  ADD COLUMN user_id INT NULL AFTER notes,
  ADD CONSTRAINT fk_oh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE measurement_file_history
  ADD COLUMN user_id INT NULL AFTER notes,
  ADD CONSTRAINT fk_mfh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

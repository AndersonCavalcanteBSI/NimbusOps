ALTER TABLE measurement_files
  ADD COLUMN analyzed_by INT NULL AFTER analyzed_at,
  ADD CONSTRAINT fk_mf_analyzed_by FOREIGN KEY (analyzed_by) REFERENCES users(id) ON DELETE SET NULL;

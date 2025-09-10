ALTER TABLE operations
  ADD COLUMN next_measurement_at DATE NULL AFTER due_date;

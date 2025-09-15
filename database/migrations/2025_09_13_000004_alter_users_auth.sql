ALTER TABLE users
  ADD COLUMN email_normalized VARCHAR(180) GENERATED ALWAYS AS (LOWER(TRIM(email))) STORED,
  ADD COLUMN password_hash VARCHAR(255) NULL,
  ADD COLUMN last_login_at TIMESTAMP NULL,
  ADD COLUMN ms_linked TINYINT(1) NOT NULL DEFAULT 0,
  ADD UNIQUE KEY uniq_users_email_norm (email_normalized),
  ADD UNIQUE KEY uniq_users_entra (entra_object_id);

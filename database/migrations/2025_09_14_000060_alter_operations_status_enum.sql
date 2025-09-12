ALTER TABLE operations
  MODIFY status ENUM('draft','active','settled','canceled','pending','rejected','completed')
  NOT NULL DEFAULT 'draft';

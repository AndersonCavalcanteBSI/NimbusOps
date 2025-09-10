INSERT INTO users (name, email, active)
VALUES ('Dev Analyst', 'dev@nimbusops.local', 1)
ON DUPLICATE KEY UPDATE name=VALUES(name);

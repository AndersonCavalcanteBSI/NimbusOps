INSERT INTO operations (code, title, status, issuer, due_date, amount) VALUES
('OP-0001', 'Operação Alpha', 'active', 'Empresa A', '2025-12-31', 1500000.00),
('OP-0002', 'Operação Beta', 'draft', 'Empresa B', NULL, 500000.00),
('OP-0003', 'Operação Gama', 'settled', 'Empresa C', '2024-06-30', 2500000.00);

INSERT INTO operation_history (operation_id, action, notes) VALUES
(1, 'created', 'Operação criada'),
(1, 'status_changed', 'Status alterado para active'),
(3, 'settled', 'Operação liquidada');

INSERT INTO operation_history (operation_id, action, notes, created_at) VALUES
(1, 'measurement', 'Medição mensal', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 'measurement', 'Medição mensal', NOW()),
(2, 'measurement', 'Medição inicial', DATE_SUB(NOW(), INTERVAL 10 DAY));

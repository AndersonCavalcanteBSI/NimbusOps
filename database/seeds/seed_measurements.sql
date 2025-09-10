INSERT INTO measurement_files (operation_id, filename, storage_path, uploaded_at, analyzed_at, notes) VALUES
(1,'medicao_2025-08.pdf','/storage/ops/1/medicao_2025-08.pdf', NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 19 DAY,'Medição agosto'),
(1,'medicao_2025-09.pdf','/storage/ops/1/medicao_2025-09.pdf', NOW() - INTERVAL 5 DAY, NULL,'Aguardando análise');

INSERT INTO measurement_file_history (measurement_file_id, action, notes, created_at) VALUES
(1, 'uploaded', 'Arquivo enviado', NOW() - INTERVAL 20 DAY),
(1, 'analyzed', 'Arquivo analisado sem ressalvas', NOW() - INTERVAL 19 DAY),
(2, 'uploaded', 'Arquivo enviado', NOW() - INTERVAL 5 DAY);

-- opcional: próxima medição
UPDATE operations SET next_measurement_at = DATE_ADD(CURDATE(), INTERVAL 15 DAY) WHERE id=1;

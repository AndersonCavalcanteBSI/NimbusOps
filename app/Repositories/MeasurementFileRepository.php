<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class MeasurementFileRepository
{
    /** @return array<int, array> */
    public function listByOperation(int $opId): array
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('SELECT * FROM measurement_files WHERE operation_id = :id ORDER BY uploaded_at DESC');
        $st->execute([':id' => $opId]);
        return $st->fetchAll();
    }

    public function hasPendingAnalysis(int $opId): bool
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('SELECT 1 FROM measurement_files WHERE operation_id = :id AND analyzed_at IS NULL LIMIT 1');
        $st->execute([':id' => $opId]);
        return (bool)$st->fetchColumn();
    }

    public function markAnalyzed(int $fileId, int $userId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('UPDATE measurement_files SET analyzed_at = NOW(), analyzed_by = :u WHERE id = :id');
            $st->execute([':u' => $userId, ':id' => $fileId]);

            // log no histórico do arquivo
            $mfh = new MeasurementFileHistoryRepository();
            $mfh->log($fileId, 'analyzed', 'Arquivo analisado', $userId);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function create(int $opId, string $filename, string $storagePath, ?int $uploadedBy): int
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('INSERT INTO measurement_files (operation_id, filename, storage_path, uploaded_by) VALUES (:op,:fn,:sp,:ub)');
        $st->execute([':op' => $opId, ':fn' => $filename, ':sp' => $storagePath, ':ub' => $uploadedBy]);
        return (int)$pdo->lastInsertId();
    }
}

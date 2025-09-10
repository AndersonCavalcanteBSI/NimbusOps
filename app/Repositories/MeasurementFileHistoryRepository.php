<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class MeasurementFileHistoryRepository
{
    /** @return array<int, array> */
    public function listByFile(int $fileId): array
    {
        $pdo = Database::pdo();
        $sql = 'SELECT h.*, u.name AS user_name
                FROM measurement_file_history h
                LEFT JOIN users u ON u.id = h.user_id
                WHERE h.measurement_file_id = :id
                ORDER BY h.created_at DESC';
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $fileId]);
        return $st->fetchAll();
    }

    /**
     * @param int[] $fileIds
     * @return array<int, array<int, array>>
     */
    public function listByFiles(array $fileIds): array
    {
        if (!$fileIds) return [];
        $in = implode(',', array_fill(0, count($fileIds), '?'));
        $pdo = Database::pdo();
        $sql = "SELECT h.*, u.name AS user_name
                FROM measurement_file_history h
                LEFT JOIN users u ON u.id = h.user_id
                WHERE h.measurement_file_id IN ($in)
                ORDER BY h.created_at DESC";
        $st = $pdo->prepare($sql);
        $st->execute($fileIds);
        $rows = $st->fetchAll();

        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['measurement_file_id']][] = $r;
        }
        return $map;
    }

    public function log(int $fileId, string $action, string $notes = '', ?int $userId = null): void
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('INSERT INTO measurement_file_history (measurement_file_id, action, notes, user_id) VALUES (:f, :a, :n, :u)');
        $st->execute([':f' => $fileId, ':a' => $action, ':n' => $notes, ':u' => $userId]);
    }
}

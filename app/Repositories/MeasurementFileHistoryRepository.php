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
        $st = $pdo->prepare('SELECT * FROM measurement_file_history WHERE measurement_file_id = :id ORDER BY created_at DESC');
        $st->execute([':id' => $fileId]);
        return $st->fetchAll();
    }

    /**
     * @param int[] $fileIds
     * @return array<int, array<int, array>> map[fileId] = rows
     */
    public function listByFiles(array $fileIds): array
    {
        if (!$fileIds) return [];
        $in = implode(',', array_fill(0, count($fileIds), '?'));
        $pdo = Database::pdo();
        $st = $pdo->prepare("SELECT * FROM measurement_file_history WHERE measurement_file_id IN ($in) ORDER BY created_at DESC");
        $st->execute($fileIds);
        $rows = $st->fetchAll();

        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['measurement_file_id']][] = $r;
        }
        return $map;
    }
}

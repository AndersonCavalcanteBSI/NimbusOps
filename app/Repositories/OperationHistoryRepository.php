<?php

declare(strict_types=1);


namespace App\Repositories;


use Core\Database;


final class OperationHistoryRepository
{
    /** @return array<int, array> */
    public function listByOperation(int $opId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM operation_history WHERE operation_id = :id ORDER BY created_at DESC');
        $stmt->execute([':id' => $opId]);
        return $stmt->fetchAll();
    }
}

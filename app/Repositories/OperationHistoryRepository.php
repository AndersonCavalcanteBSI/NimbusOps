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
        // opcional: exibir nome de usuário se existir
        $sql = 'SELECT h.*, u.name AS user_name
                FROM operation_history h
                LEFT JOIN users u ON u.id = h.user_id
                WHERE h.operation_id = :id
                ORDER BY h.created_at DESC';
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $opId]);
        return $st->fetchAll();
    }

    public function log(int $opId, string $action, string $notes = '', ?int $userId = null): void
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('INSERT INTO operation_history (operation_id, action, notes, user_id) VALUES (:op, :a, :n, :u)');
        $st->execute([':op' => $opId, ':a' => $action, ':n' => $notes, ':u' => $userId]);
    }
}

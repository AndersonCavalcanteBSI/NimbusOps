<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class OperationNotifyRepository
{
    /** Lista usuários (id, name, email) que devem ser notificados na reprovação */
    public function listRecipients(int $operationId): array
    {
        $pdo = Database::pdo();
        $sql = 'SELECT u.id, u.name, u.email
                  FROM operation_rejection_notify_users rn
                  JOIN users u ON u.id = rn.user_id
                 WHERE rn.operation_id = :op
                   AND u.active = 1
                 ORDER BY u.name ASC';
        $st = $pdo->prepare($sql);
        $st->execute([':op' => $operationId]);
        return $st->fetchAll() ?: [];
    }

    /** (LEGADO) Mantida para compatibilidade com códigos antigos */
    public function setRecipients(int $operationId, array $userIds): void
    {
        $this->replaceRecipients($operationId, $userIds);
    }

    /** Substitui todos os destinatários de uma operação */
    public function replaceRecipients(int $operationId, array $userIds): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM operation_rejection_notify_users WHERE operation_id = :op')
                ->execute([':op' => $operationId]);

            $ins = $pdo->prepare(
                'INSERT IGNORE INTO operation_rejection_notify_users (operation_id, user_id) VALUES (:op, :u)'
            );
            foreach (array_unique(array_map('intval', $userIds)) as $uid) {
                if ($uid > 0) {
                    $ins->execute([':op' => $operationId, ':u' => $uid]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

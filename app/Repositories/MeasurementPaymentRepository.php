<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;
use PDO;

final class MeasurementPaymentRepository
{
    /** @param array<int, array{pay_date:string, amount:float, method?:string, notes?:string}> $rows */
    public function createMany(int $opId, int $fileId, array $rows, ?int $createdBy): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $sql = 'INSERT INTO measurement_payments (operation_id, measurement_file_id, pay_date, amount, method, notes, created_by)
                    VALUES (:op,:mf,:dt,:am,:me,:no,:cb)';
            $st = $pdo->prepare($sql);
            $count = 0;
            foreach ($rows as $r) {
                if (empty($r['pay_date']) || (float)$r['amount'] <= 0) {
                    continue;
                }
                $st->execute([
                    ':op' => $opId,
                    ':mf' => $fileId,
                    ':dt' => $r['pay_date'],
                    ':am' => (float)$r['amount'],
                    ':me' => $r['method'] ?? null,
                    ':no' => $r['notes'] ?? null,
                    ':cb' => $createdBy,
                ]);
                $count++;
            }
            $pdo->commit();
            return $count;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<int, array> */
    public function listByMeasurement(int $fileId): array
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('SELECT * FROM measurement_payments WHERE measurement_file_id = :id ORDER BY pay_date ASC, id ASC');
        $st->execute([':id' => $fileId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

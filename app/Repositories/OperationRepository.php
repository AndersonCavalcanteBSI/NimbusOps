<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class OperationRepository
{
    /** @return array{data: array<int, array>, total: int, page: int, per_page: int} */
    public function paginate(array $filters, int $page = 1, int $perPage = 10, string $orderBy = 'created_at', string $dir = 'desc'): array
    {
        $pdo = Database::pdo();

        // agora suportamos ordenar por "last_measurement_at"
        $validOrder = ['id', 'code', 'title', 'status', 'due_date', 'amount', 'created_at', 'last_measurement_at'];
        if (!in_array($orderBy, $validOrder, true)) {
            $orderBy = 'created_at';
        }
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        $where = [];
        $params = [];

        if ($q = trim((string)($filters['q'] ?? ''))) {
            $where[] = '(o.code LIKE :q OR o.title LIKE :q)';
            $params[':q'] = "%$q%";
        }
        if ($status = ($filters['status'] ?? '')) {
            $where[] = 'o.status = :status';
            $params[':status'] = $status;
        }
        if ($from = ($filters['from'] ?? '')) {
            $where[] = 'o.due_date >= :from';
            $params[':from'] = $from;
        }
        if ($to = ($filters['to'] ?? '')) {
            $where[] = 'o.due_date <= :to';
            $params[':to'] = $to;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // COUNT
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM operations o $whereSql");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);

        // SELECT com subquery para última medição (action='measurement')
        $sql = "
            SELECT
              o.*,
              (
                SELECT MAX(h.created_at)
                FROM operation_history h
                WHERE h.operation_id = o.id AND h.action = 'measurement'
              ) AS last_measurement_at
            FROM operations o
            $whereSql
            ORDER BY $orderBy $dir
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll();

        return compact('data') + ['total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function find(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM operations WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findWithMetrics(int $id): ?array
    {
        $pdo = Database::pdo();
        $sql = "
        SELECT
          o.*,
          (
            SELECT MAX(h.created_at)
            FROM operation_history h
            WHERE h.operation_id = o.id AND h.action = 'measurement'
          ) AS last_measurement_at
        FROM operations o
        WHERE o.id = :id
        LIMIT 1
    ";
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        if (!$row) return null;

        // next_measurement_at pode estar nulo => fallback (30 dias após a última medição, se existir)
        if (empty($row['next_measurement_at']) && !empty($row['last_measurement_at'])) {
            $ts = strtotime($row['last_measurement_at'] . ' +30 days');
            $row['next_measurement_at'] = $ts ? date('Y-m-d', $ts) : null;
        }

        return $row;
    }

    public function create(array $data): int
    {
        $pdo = \Core\Database::pdo();

        // Garantir unicidade do código
        $chk = $pdo->prepare('SELECT 1 FROM operations WHERE code = :code LIMIT 1');
        $chk->execute([':code' => $data['code']]);
        if ($chk->fetchColumn()) {
            throw new \InvalidArgumentException('Código já utilizado por outra operação.');
        }

        $sql = 'INSERT INTO operations
            (code, title, status, issuer, due_date, amount,
             responsible_user_id, stage2_reviewer_user_id, stage3_reviewer_user_id,
             payment_manager_user_id, payment_finalizer_user_id, rejection_notify_user_id)
            VALUES
            (:code, :title, :status, :issuer, :due_date, :amount,
             :u1, :u2, :u3, :u4, :u5, :u6)';

        $st = $pdo->prepare($sql);
        $st->execute([
            ':code'   => $data['code'],
            ':title'  => $data['title'],
            ':status' => $data['status'] ?? 'draft',
            ':issuer' => $data['issuer'] ?: null,
            ':due_date' => $data['due_date'] ?: null,
            ':amount' => $data['amount'] !== '' ? (float)$data['amount'] : null,
            ':u1' => $data['responsible_user_id'] ?: null,
            ':u2' => $data['stage2_reviewer_user_id'] ?: null,
            ':u3' => $data['stage3_reviewer_user_id'] ?: null,
            ':u4' => $data['payment_manager_user_id'] ?: null,
            ':u5' => $data['payment_finalizer_user_id'] ?: null,
            ':u6' => $data['rejection_notify_user_id'] ?: null,
        ]);

        return (int)$pdo->lastInsertId();
    }
}

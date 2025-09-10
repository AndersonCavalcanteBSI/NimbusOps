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
        $validOrder = ['id', 'code', 'title', 'status', 'due_date', 'amount', 'created_at'];
        if (!in_array($orderBy, $validOrder, true)) {
            $orderBy = 'created_at';
        }
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        $where = [];
        $params = [];
        if ($q = trim((string)($filters['q'] ?? ''))) {
            $where[] = '(code LIKE :q OR title LIKE :q)';
            $params[':q'] = "%$q%";
        }
        if ($s = ($filters['status'] ?? '')) {
            $where[] = 'status = :status';
            $params[':status'] = $s;
        }
        if ($from = ($filters['from'] ?? '')) {
            $where[] = 'due_date >= :from';
            $params[':from'] = $from;
        }
        if ($to = ($filters['to'] ?? '')) {
            $where[] = 'due_date <= :to';
            $params[':to'] = $to;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $count = $pdo->prepare("SELECT COUNT(*) FROM operations $whereSql");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT * FROM operations $whereSql ORDER BY $orderBy $dir LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll();

        return ['data' => $data, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function find(int $id): ?array
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('SELECT * FROM operations WHERE id=:id');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }
}

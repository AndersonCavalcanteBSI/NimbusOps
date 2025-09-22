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

    /** Normaliza id vindo do POST/array (aceita int|string|null) */
    private function asId(int|string|null $v): ?int
    {
        $n = (int)trim((string)$v);
        return $n > 0 ? $n : null;
    }

    /** Cria operação com destinatários configuráveis */
    public function create(array $data): int
    {
        $pdo = Database::pdo();

        // Código pode ser opcional
        $code = trim((string)($data['code'] ?? ''));

        // Garantir unicidade do código apenas se informado
        if ($code !== '') {
            $chk = $pdo->prepare('SELECT 1 FROM operations WHERE code = :code LIMIT 1');
            $chk->execute([':code' => $code]);
            if ($chk->fetchColumn()) {
                throw new \InvalidArgumentException('Código já utilizado por outra operação.');
            }
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
            ':code'   => ($code !== '' ? $code : null),
            ':title'  => (string)$data['title'],
            ':status' => (string)($data['status'] ?? 'draft'),
            ':issuer' => (($data['issuer'] ?? '') !== '' ? (string)$data['issuer'] : null),
            ':due_date' => (($data['due_date'] ?? '') !== '' ? (string)$data['due_date'] : null),
            ':amount'   => (($data['amount'] ?? '') !== '' ? (float)$data['amount'] : null),

            ':u1' => $this->asId($data['responsible_user_id']       ?? null),
            ':u2' => $this->asId($data['stage2_reviewer_user_id']   ?? null),
            ':u3' => $this->asId($data['stage3_reviewer_user_id']   ?? null),
            ':u4' => $this->asId($data['payment_manager_user_id']   ?? null),
            ':u5' => $this->asId($data['payment_finalizer_user_id'] ?? null),
            ':u6' => $this->asId($data['rejection_notify_user_id']  ?? null),
        ]);

        return (int)$pdo->lastInsertId();
    }

    /** Atualiza apenas os destinatários/validadores por fase */
    public function updateRecipients(int $id, array $data): void
    {
        $pdo = Database::pdo();

        $sql = 'UPDATE operations SET
                  responsible_user_id       = :resp,
                  stage2_reviewer_user_id   = :st2,
                  stage3_reviewer_user_id   = :st3,
                  payment_manager_user_id   = :paym,
                  rejection_notify_user_id  = :rej,
                  payment_finalizer_user_id = :final
                WHERE id = :id';

        $pdo->prepare($sql)->execute([
            ':resp'  => $this->asId($data['responsible_user_id']       ?? null),
            ':st2'   => $this->asId($data['stage2_reviewer_user_id']   ?? null),
            ':st3'   => $this->asId($data['stage3_reviewer_user_id']   ?? null),
            ':paym'  => $this->asId($data['payment_manager_user_id']   ?? null),
            ':rej'   => $this->asId($data['rejection_notify_user_id']  ?? null),
            ':final' => $this->asId($data['payment_finalizer_user_id'] ?? null),
            ':id'    => $id,
        ]);
    }

    public function fetchLastCode(): ?string
    {
        $pdo = \Core\Database::pdo();
        // Pega o último código não-nulo pela ordem de criação
        $st = $pdo->query("SELECT code FROM operations WHERE code IS NOT NULL AND code <> '' ORDER BY id DESC LIMIT 1");
        $v = $st->fetchColumn();
        return $v !== false ? (string)$v : null;
    }

    /**
     * Gera o próximo código a partir do último.
     * Regras:
     * - Se terminar em número (ex.: "OP-0012" ou "2025-0007"), incrementa preservando zeros.
     * - Se for apenas número, incrementa (0012 -> 0013).
     * - Se não houver último, começa em "0001".
     */
    public function generateNextCode(): string
    {
        $last = $this->fetchLastCode();
        if (!$last) return '0001';

        if (preg_match('/^(.*?)(\d+)$/', $last, $m)) {
            $prefix = $m[1];
            $num    = $m[2];
            $next   = (string)((int)$num + 1);
            // preserva largura com zeros à esquerda
            $next   = str_pad($next, strlen($num), '0', STR_PAD_LEFT);
            return $prefix . $next;
        }

        // Se não terminar em número, anexa "-0001"
        return rtrim($last, '-') . '-0001';
    }
}

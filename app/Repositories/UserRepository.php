<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class UserRepository
{
    /** Normaliza e-mails para comparação case-insensitive. */
    private function norm(string $email): string
    {
        return strtolower(trim($email));
    }

    public function findBasic(int $id): ?array
    {
        $pdo = Database::pdo();
        // Inclui avatar, ms_linked, active e last_login_at para a área de perfil
        $st = $pdo->prepare('
            SELECT id, name, email, avatar, role, active, ms_linked, last_login_at
              FROM users
             WHERE id = :id
             LIMIT 1
        ');
        $st->execute([':id' => $id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** Lista simples de ativos (campos básicos) */
    public function allActive(): array
    {
        $pdo = Database::pdo();
        $st = $pdo->query('SELECT id, name, email, role FROM users WHERE active = 1 ORDER BY name ASC');
        return $st->fetchAll() ?: [];
    }

    /**
     * (Opcional) Lista básica de usuários para combos/selects.
     * @param bool $onlyActive Se true, retorna somente ativos; caso contrário, todos.
     * @return array<int, array{id:int, name:string}>
     */
    public function listAllBasic(bool $onlyActive = true): array
    {
        $pdo = Database::pdo();
        if ($onlyActive) {
            $st = $pdo->query('SELECT id, name FROM users WHERE active = 1 ORDER BY name ASC');
        } else {
            $st = $pdo->query('SELECT id, name FROM users ORDER BY name ASC');
        }
        return $st->fetchAll() ?: [];
    }

    /** Busca ativo por e-mail (case-insensitive) */
    public function findByEmailActive(string $email): ?array
    {
        $pdo = Database::pdo();
        // Usa a coluna gerada email_normalized para aproveitar o índice
        $st = $pdo->prepare('
            SELECT *
              FROM users
             WHERE email_normalized = lcase(trim(:e))
               AND active = 1
             LIMIT 1
        ');
        $st->execute([':e' => $this->norm($email)]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Busca ativo por Entra Object Id (vínculo Microsoft) — mantido por compatibilidade */
    public function findByEntraIdActive(string $entraId): ?array
    {
        if ($entraId === '') {
            return null;
        }
        $pdo = Database::pdo();
        $st = $pdo->prepare('SELECT * FROM users WHERE entra_object_id = :eo AND active = 1 LIMIT 1');
        $st->execute([':eo' => $entraId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Cria usuário local com senha (retorna id) */
    public function createLocal(string $name, string $email, string $plainPassword, bool $active = true): int
    {
        $pdo = Database::pdo();
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $st = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, active, created_at)
             VALUES (:n,:e,:p,"user",:a,NOW())'
        );
        $st->execute([
            ':n' => $name,
            ':e' => trim($email),
            ':p' => $hash,
            ':a' => $active ? 1 : 0,
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** Define/atualiza a senha local (nome antigo mantido) */
    public function setPassword(int $userId, string $plainPassword): void
    {
        $pdo = Database::pdo();
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $st = $pdo->prepare('UPDATE users SET password_hash = :p WHERE id = :id');
        $st->execute([':p' => $hash, ':id' => $userId]);
    }

    /** Atualiza a senha local (novo nome usado no ProfileController) */
    public function updatePassword(int $userId, string $newPlain): void
    {
        $this->setPassword($userId, $newPlain);
    }

    /** Verifica credenciais locais */
    public function verifyLocalLogin(string $email, string $plainPassword): ?array
    {
        $pdo = Database::pdo();
        // Usa o índice email_normalized
        $st = $pdo->prepare('
            SELECT id, name, email, avatar, role, active, ms_linked, password_hash, last_login_at
              FROM users
             WHERE email_normalized = lcase(trim(:e))
               AND active = 1
             LIMIT 1
        ');
        $st->execute([':e' => trim($email)]);
        $u = $st->fetch();
        if (!$u) {
            return null;
        }

        $hash = (string)($u['password_hash'] ?? '');
        if ($hash !== '' && password_verify($plainPassword, $hash)) {
            return $u;
        }

        // fallback legacy (se existir coluna 'password' e contiver hash compatível)
        $legacy = (string)($u['password'] ?? '');
        if ($legacy !== '' && password_verify($plainPassword, $legacy)) {
            return $u;
        }

        return null;
    }

    /** Liga conta Microsoft ao usuário local — mantido por compatibilidade */
    public function attachEntraId(int $userId, string $entraId): void
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('UPDATE users SET entra_object_id = :eo, ms_linked = 1 WHERE id = :id');
        $st->execute([':eo' => $entraId, ':id' => $userId]);
    }

    /** Remove vínculo Microsoft — mantido por compatibilidade */
    public function detachEntraId(int $userId): void
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('UPDATE users SET entra_object_id = NULL, ms_linked = 0 WHERE id = :id');
        $st->execute([':id' => $userId]);
    }

    /** Atualiza carimbo de último login */
    public function updateLastLogin(int $userId): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute([':id' => $userId]);
    }

    public function listAll(): array
    {
        $pdo = Database::pdo();
        $st = $pdo->query('
            SELECT id, name, email, role, active, last_login_at
              FROM users
          ORDER BY name ASC
        ');
        return $st->fetchAll() ?: [];
    }

    public function findFull(int $id): ?array
    {
        $pdo = Database::pdo();
        // Mantém entra_object_id por compatibilidade, mas já inclui avatar e ms_linked
        $st = $pdo->prepare('
            SELECT id, name, email, avatar, role, active, entra_object_id, ms_linked, last_login_at
              FROM users
             WHERE id = :id
             LIMIT 1
        ');
        $st->execute([':id' => $id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /**
     * (NOVO) Atualiza campos do perfil do próprio usuário.
     * Aceita somente campos existentes: name, avatar.
     * Ex.: updateProfile($id, ['name' => 'Novo Nome', 'avatar' => '/uploads/...'])
     */
    public function updateProfile(int $id, array $data): void
    {
        $pdo    = Database::pdo();
        $sets   = [];
        $params = [':id' => $id];

        if (array_key_exists('name', $data)) {
            $sets[] = 'name = :name';
            $params[':name'] = ($data['name'] === '' ? null : (string)$data['name']);
        }
        if (array_key_exists('avatar', $data)) {
            $sets[] = 'avatar = :avatar';
            $params[':avatar'] = ($data['avatar'] === '' ? null : (string)$data['avatar']);
        }

        if (!$sets) return;

        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $pdo->prepare($sql)->execute($params);
    }

    /**
     * (RENOMEADO) Atualiza dados administrativos (nome, e-mail, role, ativo).
     * Mantido para compatibilidade com telas de administração.
     */
    public function updateProfileAdmin(int $id, string $name, string $email, string $role, bool $active): void
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('
            UPDATE users
               SET name  = :n,
                   email = :e,
                   role  = :r,
                   active= :a
             WHERE id = :id
        ');
        $st->execute([
            ':n'  => $name,
            ':e'  => trim($email),
            ':r'  => in_array($role, ['admin', 'user'], true) ? $role : 'user',
            ':a'  => $active ? 1 : 0,
            ':id' => $id,
        ]);
    }

    /** Cria usuário completo já com role e active (senha opcional) */
    public function createFull(string $name, string $email, ?string $plainPassword, string $role = 'user', bool $active = true): int
    {
        $pdo = Database::pdo();
        $hash = $plainPassword ? password_hash($plainPassword, PASSWORD_DEFAULT) : null;

        $st = $pdo->prepare('
            INSERT INTO users (name, email, password_hash, role, active, created_at)
            VALUES (:n,:e,:p,:r,:a,NOW())
        ');
        $st->execute([
            ':n' => $name,
            ':e' => trim($email),
            ':p' => $hash,
            ':r' => in_array($role, ['admin', 'user'], true) ? $role : 'user',
            ':a' => $active ? 1 : 0,
        ]);

        return (int)$pdo->lastInsertId();
    }

    public function paginateFiltered(
        array $filters,
        int $page = 1,
        int $perPage = 20,
        string $orderBy = 'name',
        string $dir = 'asc'
    ): array {
        $pdo = \Core\Database::pdo();

        $validOrder = ['id', 'name', 'email', 'role', 'active', 'last_login_at', 'created_at'];
        if (!in_array($orderBy, $validOrder, true)) $orderBy = 'name';
        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';

        $where  = [];
        $params = [];

        // q -> name OR email (com ESCAPE)
        if ($q = trim((string)($filters['q'] ?? ''))) {
            $needle = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
            $like   = "%{$needle}%";
            $esc    = " ESCAPE '\\\\' ";
            $where[]       = "(name LIKE :q1{$esc} OR email LIKE :q2{$esc})";
            $params[':q1'] = $like;
            $params[':q2'] = $like;
        }

        // role exato
        if ($role = trim((string)($filters['role'] ?? ''))) {
            $where[]         = 'role = :role';
            $params[':role'] = $role;
        }

        // active 0/1 quando fornecido
        if (array_key_exists('active', $filters) && $filters['active'] !== null) {
            $where[]            = 'active = :active';
            $params[':active']  = (int)$filters['active'];
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // total
        $count = $pdo->prepare("SELECT COUNT(*) FROM users {$whereSql}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);

        // dados
        $sql = "SELECT id, name, email, role, active, last_login_at
              FROM users
              {$whereSql}
          ORDER BY {$orderBy} {$dir}
             LIMIT :limit OFFSET :offset";
        $st = $pdo->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limit',  $perPage, \PDO::PARAM_INT);
        $st->bindValue(':offset', $offset,  \PDO::PARAM_INT);
        $st->execute();

        return [
            'data'     => $st->fetchAll() ?: [],
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }
}

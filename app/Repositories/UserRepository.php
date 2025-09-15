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
        $st = $pdo->prepare('SELECT id, name, email FROM users WHERE id = :id AND active = 1');
        $st->execute([':id' => $id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** Lista simples de ativos */
    public function allActive(): array
    {
        $pdo = Database::pdo();
        $st = $pdo->query('SELECT id, name, email FROM users WHERE active = 1 ORDER BY name ASC');
        return $st->fetchAll() ?: [];
    }

    /** Busca ativo por e-mail (sem coluna extra; normaliza no SELECT) */
    public function findByEmailActive(string $email): ?array
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'SELECT * FROM users
              WHERE LOWER(TRIM(email)) = LOWER(TRIM(:e))
                AND active = 1
              LIMIT 1'
        );
        $st->execute([':e' => $this->norm($email)]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Busca ativo por Entra Object Id (vínculo Microsoft) */
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
            'INSERT INTO users (name, email, password_hash, active, created_at)
             VALUES (:n,:e,:p,:a,NOW())'
        );
        $st->execute([
            ':n' => $name,
            ':e' => trim($email),
            ':p' => $hash,
            ':a' => $active ? 1 : 0,
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** Define/atualiza a senha local */
    public function setPassword(int $userId, string $plainPassword): void
    {
        $pdo = Database::pdo();
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $st = $pdo->prepare('UPDATE users SET password_hash = :p WHERE id = :id');
        $st->execute([':p' => $hash, ':id' => $userId]);
    }

    /** Verifica credenciais locais */
    public function verifyLocalLogin(string $email, string $plainPassword): ?array
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'SELECT * FROM users
              WHERE LOWER(TRIM(email)) = LOWER(TRIM(:e))
                AND active = 1
              LIMIT 1'
        );
        $st->execute([':e' => $this->norm($email)]);
        $user = $st->fetch() ?: null;

        if (!$user || empty($user['password_hash'])) {
            return null;
        }
        if (!password_verify($plainPassword, (string)$user['password_hash'])) {
            return null;
        }
        return $user;
    }

    /** Liga conta Microsoft ao usuário local */
    public function attachEntraId(int $userId, string $entraId): void
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('UPDATE users SET entra_object_id = :eo WHERE id = :id');
        $st->execute([':eo' => $entraId, ':id' => $userId]);
    }

    /** Remove vínculo Microsoft */
    public function detachEntraId(int $userId): void
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('UPDATE users SET entra_object_id = NULL WHERE id = :id');
        $st->execute([':id' => $userId]);
    }

    /** Atualiza carimbo de último login */
    public function updateLastLogin(int $userId): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute([':id' => $userId]);
    }
}

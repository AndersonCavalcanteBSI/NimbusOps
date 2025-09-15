<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class UserRepository
{
    public function findBasic(int $id): ?array
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'SELECT id, name, email, entra_object_id
               FROM users
              WHERE id = :id AND active = 1
              LIMIT 1'
        );
        $st->execute([':id' => $id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public function allActive(): array
    {
        $pdo = Database::pdo();
        $st = $pdo->query('SELECT id, name, email FROM users WHERE active = 1 ORDER BY name ASC');
        return $st->fetchAll() ?: [];
    }

    public function findByEmailActive(string $email): ?array
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'SELECT *
               FROM users
              WHERE active = 1
                AND LOWER(TRIM(email)) = LOWER(TRIM(:email))
              LIMIT 1'
        );
        $st->execute([':email' => $email]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findByEntraIdActive(string $entraId): ?array
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'SELECT *
               FROM users
              WHERE active = 1
                AND entra_object_id = :eo
              LIMIT 1'
        );
        $st->execute([':eo' => $entraId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function attachEntraId(int $userId, string $entraId): void
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('UPDATE users SET entra_object_id = :eo WHERE id = :id');
        $st->execute([':eo' => $entraId, ':id' => $userId]);
    }
}

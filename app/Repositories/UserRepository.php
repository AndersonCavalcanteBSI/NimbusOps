<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class UserRepository
{
    public function findBasic(int $id): ?array
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('SELECT id, name, email FROM users WHERE id = :id AND active = 1');
        $st->execute([':id' => $id]);
        $r = $st->fetch();
        return $r ?: null;
    }
}

<?php

declare(strict_types=1);

namespace App\Security;

final class CurrentUser
{
    public static function id(): ?int
    {
        // Se um dia você ativar sessão: return $_SESSION['user_id'] ?? null;
        // Por enquanto: usa .env DEV_USER_ID em ambiente local (fallback para 1)
        if (($_ENV['APP_ENV'] ?? 'local') === 'local') {
            $id = (int)($_ENV['DEV_USER_ID'] ?? 1);
            return $id > 0 ? $id : null;
        }
        return null;
    }

    public static function isDev(): bool
    {
        return (($_ENV['APP_ENV'] ?? 'local') === 'local');
    }
}

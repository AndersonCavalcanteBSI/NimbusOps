<?php

declare(strict_types=1);


namespace App\Middlewares;


use Core\Middleware;


final class AuthMiddleware implements Middleware
{
    public function handle(): void
    {
        // Fase 1: desabilitado (somente esqueleto)
        // Exemplo para fase 2:
        // if (!isset($_SESSION['user'])) { header('Location: /login'); exit; }
    }
}

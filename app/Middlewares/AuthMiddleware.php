<?php

declare(strict_types=1);

namespace App\Middlewares;

use Core\Middleware;

final class AuthMiddleware implements Middleware
{
    public function handle(): void
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            session_start();
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Rotas/paths públicos
        $publicPaths = [
            '/auth/login',
            '/auth/callback',
        ];

        // Arquivos estáticos
        $isAsset = str_starts_with($path, '/uploads/')
            || str_starts_with($path, '/assets/')
            || str_starts_with($path, '/favicon')
            || ($path === '/');

        if (in_array($path, $publicPaths, true) || $isAsset) {
            return;
        }

        // Requer sessão
        if (!isset($_SESSION['user'])) {
            header('Location: /auth/login');
            exit;
        }
    }
}

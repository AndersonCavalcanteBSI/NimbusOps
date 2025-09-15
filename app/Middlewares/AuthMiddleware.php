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

        $path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Rotas públicas (login local e logout)
        $publicPaths = [
            '/auth/login',   // formulário (GET) e envio (POST)
            '/logout',       // evita loop caso a sessão expire
            // '/auth/callback', // descomente se/quando usar Microsoft
        ];

        // Arquivos estáticos
        $isAsset = str_starts_with($path, '/uploads/')
            || str_starts_with($path, '/assets/')
            || str_starts_with($path, '/favicon');

        if (in_array($path, $publicPaths, true) || $isAsset) {
            return; // não exige sessão
        }

        // Exige sessão para todo o resto
        if (!isset($_SESSION['user'])) {
            header('Location: /auth/login');
            exit;
        }
    }
}

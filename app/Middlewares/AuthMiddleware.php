<?php

declare(strict_types=1);

namespace App\Middlewares;

use Core\Middleware;

final class AuthMiddleware implements Middleware
{
    public function handle(): void
    {
        // Garante sessão iniciada (parâmetros do cookie já podem ter sido definidos no index.php)
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            session_start();
        }

        $path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Libera arquivos estáticos
        if (preg_match('#^/(uploads|assets)(/|$)#', $path) || $path === '/favicon.ico') {
            return;
        }

        // Whitelist de rotas públicas (métodos permitidos)
        $public = [
            '/auth/local'      => ['GET', 'POST'], // login local (form + submit)
            '/auth/login'      => ['GET', 'POST'], // atalho/compat para a tela de login local
            '/auth/microsoft'  => ['GET'],         // inicia OAuth MS
            '/auth/callback'   => ['GET'],         // callback MS
            '/logout'          => ['GET'],         // permitir sair mesmo sem sessão válida
        ];

        if (isset($public[$path]) && in_array($method, $public[$path], true)) {
            return; // não exige sessão
        }

        // Para todas as outras rotas, exige usuário logado
        if (empty($_SESSION['user']['id'])) {
            header('Location: /auth/local');
            exit;
        }
    }
}

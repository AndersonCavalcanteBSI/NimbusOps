<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Core\Env;
use App\Middlewares\RequireRoleMiddleware;

Env::load(dirname(__DIR__)); // carrega .env ANTES de ler APP_URL

// 1) Redireciona para HTTPS antes de abrir a sessão
$wantsHttps = str_starts_with((string)($_ENV['APP_URL'] ?? ''), 'https://');
$isHttps    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');

if ($wantsHttps && !$isHttps) {
    $host = $_SERVER['HTTP_HOST']
        ?? (parse_url((string)($_ENV['APP_URL'] ?? ''), PHP_URL_HOST) ?: 'nimbusops.local');
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

// 2) Inicia a sessão já no protocolo final, com cookie robusto (sem domain fixo)
if (session_status() !== PHP_SESSION_ACTIVE) {
    // Detecta se a requisição atual está em HTTPS
    $isHttpsNow = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        // IMPORTANTE: não defina 'domain' -> cookie “host-only”
        'secure'   => $isHttpsNow,  // true somente se a conexão atual for HTTPS
        'httponly' => true,
        'samesite' => 'Lax',        // permite retorno de redirects/login
    ]);

    // Redundâncias seguras
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    if ($isHttpsNow) {
        ini_set('session.cookie_secure', '1');
    }

    session_start();
}


use Core\Router;

// Middlewares
use App\Middlewares\SecurityHeadersMiddleware;
use App\Middlewares\CORSMiddleware;
use App\Middlewares\AuthMiddleware;

// Controllers
use App\Controllers\HomeController;
use App\Controllers\OperationController;
use App\Controllers\MeasurementController;
use App\Controllers\AuthController;

// Middlewares globais
(new SecurityHeadersMiddleware())->handle();
(new CORSMiddleware())->handle();
(new AuthMiddleware())->handle(); // proteção por sessão (com exceções internas)

// Router
$router = new Router();

// Helper p/ proteger rotas apenas para administradores
$adminOnly = fn(callable $cb) => function () use ($cb) {
    (new RequireRoleMiddleware(['admin']))->handle();
    $cb();
};

/**
 * Rotas públicas de autenticação
 */
/*$router->get('/auth/login', fn() => (new AuthController())->login());
$router->post('/auth/login', fn() => (new AuthController())->loginPost());
//$router->get('/auth/microsoft', fn() => (new AuthController())->microsoftStart());
//$router->get('/auth/callback', fn() => (new AuthController())->callback());
$router->get('/logout', fn() => (new AuthController())->logout());*/

// Login LOCAL
$router->get('/auth/local', fn() => (new AuthController())->localForm());
$router->post('/auth/local', fn() => (new AuthController())->loginPost());

// Microsoft (quando for reativar)
$router->get('/auth/login', fn() => (new AuthController())->login());      // inicia OAuth MS
// $router->get('/auth/callback', fn() => (new AuthController())->callback());

$router->get('/logout', fn() => (new AuthController())->logout());


/**
 * Rotas da aplicação
 */
$router->get('/', fn() => (new HomeController())->index());

$router->get('/operations', fn() => (new OperationController())->index());
$router->get('/operations/{id}', fn(string $id) => (new OperationController())->show((int)$id));

// Upload
$router->get('/measurements/upload', fn() => (new MeasurementController())->create());
$router->post('/measurements/upload', fn() => (new MeasurementController())->store());

// Review 1 (atalho)
$router->get('/measurements/{id}/review', fn(string $id) => (new MeasurementController())->reviewForm((int)$id, 1));
$router->post('/measurements/{id}/review', fn(string $id) => (new MeasurementController())->reviewSubmit((int)$id, 1));

// Review com estágio explícito (2ª, 3ª, 4ª…)
$router->get('/measurements/{id}/review/{stage}', fn(string $id, string $stage) => (new MeasurementController())->reviewForm((int)$id, (int)$stage));
$router->post('/measurements/{id}/review/{stage}', fn(string $id, string $stage) => (new MeasurementController())->reviewSubmit((int)$id, (int)$stage));

// Atalho legado para "analyze" -> redireciona para review/1
$router->post('/measurements/{id}/analyze', function (string $id) {
    header('Location: /measurements/' . (int)$id . '/review/1');
    exit;
});

// Pagamentos (Fase 6)
$router->get('/measurements/{id}/payments/new', fn(string $id) => (new MeasurementController())->paymentsForm((int)$id));
$router->post('/measurements/{id}/payments',    fn(string $id) => (new MeasurementController())->paymentsStore((int)$id));

// Finalização (Fase 7)
$router->get('/measurements/{id}/finalize', fn(string $id) => (new MeasurementController())->finalizeForm((int)$id));
$router->post('/measurements/{id}/finalize', fn(string $id) => (new MeasurementController())->finalizeSubmit((int)$id));

// Criar operação
$router->get('/operations/create', $adminOnly(fn() => (new OperationController())->create()));
$router->post('/operations',       $adminOnly(fn() => (new OperationController())->store()));

// Compat antigo GET "analyzed" -> review/1
$router->get('/measurements/{id}/analyzed', function (string $id) {
    header('Location: /measurements/' . (int)$id . '/review/1');
    exit;
});

$router->dispatch();

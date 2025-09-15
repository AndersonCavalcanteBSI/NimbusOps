<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Core\Env;

Env::load(dirname(__DIR__)); // carrega .env ANTES de ler APP_URL

// Sessão (cookie consistente p/ domínio local)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => 'nimbusops.local', // seu host local
        'secure'   => true,               // usamos https
        'httponly' => true,
        'samesite' => 'Lax',              // ok para redirect GET
    ]);
    session_start();
}

// Força https se APP_URL pedir https
$wantsHttps = str_starts_with((string)($_ENV['APP_URL'] ?? ''), 'https://');
$isHttps    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');
if ($wantsHttps && !$isHttps) {
    $host = $_SERVER['HTTP_HOST'] ?? 'nimbusops.local';
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $uri, true, 301);
    exit;
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
(new AuthMiddleware())->handle(); // << habilita proteção por sessão

$router = new Router();

/**
 * Rotas públicas de autenticação
 */
$router->get('/auth/login', fn() => (new AuthController())->login());
$router->get('/auth/callback', fn() => (new AuthController())->callback());
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
$router->get('/operations/create', fn() => (new OperationController())->create());
$router->post('/operations', fn() => (new OperationController())->store());

// Compat antigo GET "analyzed" -> review/1
$router->get('/measurements/{id}/analyzed', function (string $id) {
    header('Location: /measurements/' . (int)$id . '/review/1');
    exit;
});

$router->dispatch();

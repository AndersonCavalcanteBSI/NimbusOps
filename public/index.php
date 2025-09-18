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
    $isHttpsNow = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttpsNow,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
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
use App\Controllers\UserController;

// Middlewares globais
(new SecurityHeadersMiddleware())->handle();
(new CORSMiddleware())->handle();
(new AuthMiddleware())->handle(); // proteção por sessão (com exceções internas)

// Router
$router = new Router();

// Helper p/ proteger rotas apenas para administradores
$adminOnly = fn(callable $cb) => function (...$args) use ($cb) {
    (new RequireRoleMiddleware(['admin']))->handle();
    $cb(...$args);
};

/**
 * Rotas públicas de autenticação
 */
// Login LOCAL (form + submit)
$router->get('/auth/local', fn() => (new AuthController())->localForm());
$router->post('/auth/local', fn() => (new AuthController())->loginPost());

// Opcional: /auth/login também mostra o mesmo form (compat)
$router->get('/auth/login', fn() => (new AuthController())->localForm());

// Fluxo Microsoft (liberado no middleware)
$router->get('/auth/microsoft', fn() => (new AuthController())->microsoftStart());
$router->get('/auth/callback',  fn() => (new AuthController())->microsoftCallback());

// Logout
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

// **NOVO**: Histórico da medição (somente leitura)
$router->get('/measurements/{id}/history', fn(string $id) => (new MeasurementController())->history((int)$id));

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

// Usuários (apenas admin)
$router->get('/users',               $adminOnly(fn() => (new UserController())->index()));
$router->get('/users/create',        $adminOnly(fn() => (new UserController())->create()));
$router->post('/users',              $adminOnly(fn() => (new UserController())->store()));
$router->get('/users/{id}/edit',     $adminOnly(fn(string $id) => (new UserController())->edit((int)$id)));
$router->post('/users/{id}',         $adminOnly(fn(string $id) => (new UserController())->update((int)$id)));

// Vínculo Microsoft (desvincular)
$router->get('/auth/microsoft/unlink', fn() => (new AuthController())->unlinkMicrosoft());

// Compat antigo GET "analyzed" -> review/1
$router->get('/measurements/{id}/analyzed', function (string $id) {
    header('Location: /measurements/' . (int)$id . '/review/1');
    exit;
});

$router->dispatch();

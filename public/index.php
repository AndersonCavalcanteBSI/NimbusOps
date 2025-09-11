<?php

declare(strict_types=1);

use Core\Env;
use Core\Router;
use App\Middlewares\SecurityHeadersMiddleware;
use App\Middlewares\CORSMiddleware;
use App\Controllers\HomeController;
use App\Controllers\OperationController;
use App\Controllers\MeasurementController;

require __DIR__ . '/../vendor/autoload.php';
Env::load(dirname(__DIR__));

// Middlewares globais
(new SecurityHeadersMiddleware())->handle();
(new CORSMiddleware())->handle();

$router = new Router();

// Rotas
$router->get('/', fn() => (new HomeController())->index());
$router->get('/operations', fn() => (new OperationController())->index());
$router->get('/operations/{id}', fn(string $id) => (new OperationController())->show((int)$id));
//$router->post('/measurements/{id}/analyze', fn(string $id) => (new \App\Controllers\OperationController())->analyzeFile((int)$id));
$router->post('/measurements/{id}/analyze', function (string $id) {
    header('Location: /measurements/' . (int)$id . '/review/1');
    exit;
});

// Upload
$router->get('/measurements/upload', fn() => (new MeasurementController())->create());
$router->post('/measurements/upload', fn() => (new MeasurementController())->store());

// Review 1 (atalhos)
$router->get('/measurements/{id}/review', fn(string $id) => (new MeasurementController())->reviewForm((int)$id, 1));
$router->post('/measurements/{id}/review', fn(string $id) => (new MeasurementController())->reviewSubmit((int)$id, 1));

// Review com estágio explícito (2ª, 3ª…)
$router->get('/measurements/{id}/review/{stage}', fn(string $id, string $stage) => (new MeasurementController())->reviewForm((int)$id, (int)$stage));
$router->post('/measurements/{id}/review/{stage}', fn(string $id, string $stage) => (new MeasurementController())->reviewSubmit((int)$id, (int)$stage));

// Compat antigos
$router->get('/measurements/{id}/analyzed', function (string $id) {
    header('Location: /measurements/' . (int)$id . '/review/1');
    exit;
});

// Se ainda houver POST antigo para /analyze, redirecione (ou remova se não usar)
$router->post('/measurements/{id}/analyze', function (string $id) {
    header('Location: /measurements/' . (int)$id . '/review/1');
    exit;
});

$router->dispatch();

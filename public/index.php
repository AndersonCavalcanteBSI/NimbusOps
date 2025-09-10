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
$router->post('/measurements/{id}/analyze', fn(string $id) => (new \App\Controllers\OperationController())->analyzeFile((int)$id));
$router->get('/measurements/upload', fn() => (new MeasurementController())->create());
$router->post('/measurements/upload', fn() => (new MeasurementController())->store());

$router->dispatch();

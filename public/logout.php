<?php

declare(strict_types=1);

use Core\Env;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../vendor/autoload.php';
Env::load(dirname(__DIR__)); // carrega .env a partir da raiz do projeto

// Delega para o controller já existente
$controller = new \App\Controllers\AuthController();
$controller->logout();

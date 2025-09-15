<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Core\Env;

Env::load(dirname(__DIR__, 2));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => 'nimbusops.local',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$controller = new \App\Controllers\AuthController();
$controller->callback();

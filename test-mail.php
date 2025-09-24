<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Core\GraphMailer;

// Carrega as variáveis do .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Agora pode usar
$mailer = new GraphMailer();
$mailer->send(
    "anderson.cavalcante@bsicapital.com.br",
    "Teste",
    "Teste NimbusOps",
    "<p>Funcionou 🎉</p>"
);

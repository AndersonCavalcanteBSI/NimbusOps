<?php $role = $_SESSION['user']['role'] ?? 'user'; ?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>NimbusOps</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>

<body>
    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/">NimbusOps</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="/operations">Operações</a></li>
                    <li class="nav-item"><a class="nav-link" href="/measurements/upload">Medições</a></li>
                    <!--<li class="nav-item"><a class="nav-link" href="/operations/create">Nova Operação</a></li>-->
                    <?php if ($role === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="/operations/create">Nova Operação</a></li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <?php if (!empty($_SESSION['user'])): ?>
                        <li class="nav-item">
                            <span class="nav-link disabled">
                                <?= htmlspecialchars($_SESSION['user']['name'] ?? $_SESSION['user']['email'] ?? 'Usuário') ?>
                            </span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/logout">Sair</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="/auth/login">Entrar</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container py-4">
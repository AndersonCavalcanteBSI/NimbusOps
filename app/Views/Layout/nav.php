<?php
$path = $_SERVER['REQUEST_URI'] ?? '/';
$active = fn(string $needle) => str_starts_with($path, $needle) ? 'active' : '';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-light mb-3 border-bottom">
    <div class="container-fluid">
        <a class="navbar-brand" href="/">NimbusOps</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav"
            aria-controls="topNav" aria-expanded="false" aria-label="Alternar navegação">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="topNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $active('/operations') ?>" href="/operations">Operações</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active('/measurements') ?>" href="/measurements/upload">Medições</a>
                </li>
                <?php if ($role === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="/operations/create">Nova Operação</a></li>
                    <li class="nav-item"><a class="nav-link" href="/users">Usuários</a></li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto">
                <?php if (!$loggedIn): ?>
                    <li class="nav-item"><a class="nav-link <?= $active('/login') ?>" href="/login">Entrar</a></li>
                    <li class="nav-item"><a class="nav-link <?= $active('/register') ?>" href="/register">Registrar</a></li>
                <?php elseif (!$msLinked): ?>
                    <li class="nav-item"><a class="nav-link <?= $active('/auth/microsoft') ?>" href="/auth/microsoft">Vincular Microsoft</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="/profile"><?= htmlspecialchars($_SESSION['user']['name'] ?? 'Perfil') ?></a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="/logout">Sair</a></li>
            </ul>
        </div>
    </div>
</nav>
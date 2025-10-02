<?php
$path   = $_SERVER['REQUEST_URI'] ?? '/';
$active = fn(string $needle) => (str_starts_with($path, $needle) ? ' is-active' : '');
$h      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

require_once __DIR__ . '/../partials/avatar_helper.php';

// --- dados do usuário da sessão (com fallbacks) ---
$u           = $_SESSION['user'] ?? [];
$displayName = $u['name']  ?? 'Perfil';
$role        = $u['role']  ?? 'user';
$loggedIn    = isset($_SESSION['user_id']);                 // usado no template
$msLinked    = (int)($u['ms_linked'] ?? 0) === 1;           // usado no template

// Avatar com cache-buster (?v=)
$avatarPath = (string)($u['avatar'] ?? '');
$avatarVer  = (string)($u['avatar_ver'] ?? '');
if ($avatarPath !== '') {
    $avatarSrc = $h($avatarPath) . ($avatarVer !== '' ? ('?v=' . rawurlencode($avatarVer)) : '');
} else {
    $avatarSrc = avatarPlaceholder($displayName, 28);
}
?>

<nav class="navbar navbar-expand-lg topbar shadow-sm">
    <div class="container">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="/operations">
            <img src="/assets/icons/logo2.png" alt="BSI Capital" class="topbar-logo">
        </a>

        <!-- Toggler -->
        <button class="navbar-toggler topbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav"
            aria-controls="topNav" aria-expanded="false" aria-label="Alternar navegação">
            ☰
        </button>

        <div class="collapse navbar-collapse" id="topNav">
            <!-- Left -->
            <ul class="navbar-nav me-auto topnav">
                <li class="nav-item">
                    <a class="topnav-link<?= $active('/operations') ?>" href="/operations">Operações</a>
                </li>
                <li class="nav-item">
                    <a class="topnav-link<?= $active('/measurements') ?>" href="/measurements/upload">Medições</a>
                </li>
                <?php if (($role ?? 'user') === 'admin'): ?>
                    <li class="nav-item">
                        <a class="topnav-link<?= $active('/users') ?>" href="/users">Usuários</a>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Right -->
            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <?php if (!($loggedIn ?? false)): ?>
                    <li class="nav-item"><a class="btn btn-outline-brand btn-pill" href="/login">Entrar</a></li>
                <?php else: ?>
                    <?php if (!($msLinked ?? false)): ?>
                        <li class="nav-item d-none d-lg-block">
                            <!--<a class="btn btn-outline-brand btn-pill" href="/auth/microsoft">Vincular Microsoft</a>-->
                        </li>
                    <?php endif; ?>

                    <!-- Usuário (dropdown simples) -->
                    <li class="nav-item dropdown">
                        <a class="topnav-user dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= $avatarSrc ?>" alt="Avatar" class="topnav-avatar me-2" width="28" height="28" onerror="this.onerror=null;this.src='<?= avatarPlaceholder($displayName, 28) ?>';" />
                            <?= $h($displayName) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/profile">Meu perfil</a></li>
                            <?php if (!($msLinked ?? false)): ?>
                                <li><a class="dropdown-item" href="/auth/microsoft">Vincular Microsoft</a></li>
                            <?php endif; ?>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="/logout">Sair</a></li>
                        </ul>
                    </li>

                    <li class="nav-item d-lg-none">
                        <a class="topnav-link text-danger" href="/logout">Sair</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
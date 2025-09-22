<?php
$pageTitle = 'Login';
$pageCss   = ['/assets/login.css'];
include __DIR__ . '/../layout/header.php';

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="login-bg">
    <div class="card login-card p-4 p-md-5 bg-white">
        <div class="text-center mb-3">
            <img class="login-logo mb-2" src="/assets/icons/logo.png" alt="BSI Capital" style="max-height: 60px; margin-bottom: .5rem;">
            <div class="text-muted">Acesse sua conta</div>
        </div>

        <?php if ($flashError): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>

        <form method="post" action="/auth/local" novalidate>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="email">E-mail</label>
                <input type="email" class="form-control round input-shadow" id="email" name="email" placeholder="usuario@bsicapital.com.br" required>
            </div>

            <div class="mb-2">
                <label class="form-label fw-semibold" for="password">Senha</label>
                <input type="password" class="form-control round input-shadow" id="password" name="password" placeholder="••••••••" required>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label small" for="remember">Manter conectado</label>
                </div>
            </div>

            <button class="btn btn-brand btn-pill w-100 mb-3" type="submit">Entrar</button>

            <a class="btn btn-microsoft btn-pill w-100 d-flex align-items-center justify-content-center gap-2" href="/auth/microsoft">
                <svg width="18" height="18" viewBox="0 0 23 23" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect x="1" y="1" width="9.5" height="9.5" fill="#F35325" />
                    <rect x="12.5" y="1" width="9.5" height="9.5" fill="#81BC06" />
                    <rect x="1" y="12.5" width="9.5" height="9.5" fill="#05A6F0" />
                    <rect x="12.5" y="12.5" width="9.5" height="9.5" fill="#FFBA08" />
                </svg>
                <span>Continuar com a conta Microsoft</span>
            </a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
<?php
$withNav   = true;
$isEdit    = !empty($user);
$pageTitle = $isEdit ? ('Editar usuário - ' . ($user['name'] ?? '')) : 'Novo usuário';
$pageCss   = ['/assets/ops.css']; // garante estilos
include __DIR__ . '/../layout/header.php';

$h = fn($v) => htmlspecialchars((string)$v);
$role = $user['role'] ?? 'user';
$active = isset($user['active']) ? ((int)$user['active'] === 1) : true;

$msConnected = $msConnected ?? false;
$msToken     = $msToken ?? null;
?>

<!-- Hero compacto -->
<section class="ops-hero ops-hero--clean">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div class="ops-hero__stack">
                <h1 class="ops-hero__title"><?= $isEdit ? 'Editar usuário' : 'Novo usuário' ?></h1>
                <p class="ops-hero__subtitle">Defina dados de acesso, tipo e integrações</p>
            </div>
            <a href="/users" class="btn btn-brand btn-pill">‹ Voltar</a>
        </div>
    </div>
</section>

<div class="container my-4">

    <!-- Card: dados básicos -->
    <form id="userForm" method="post" action="<?= $isEdit ? '/users/' . (int)$user['id'] : '/users' ?>">
        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <div class="modal-titlebar__icon">
                    <svg width="16" height="16" viewBox="0 0 24 24">
                        <path d="M12 12c2.761 0 5-2.239 5-5S14.761 2 12 2 7 4.239 7 7s2.239 5 5 5Zm0 2c-4.418 0-8 2.239-8 5v1h16v-1c0-2.761-3.582-5-8-5Z" stroke="currentColor" stroke-width="1.4" fill="none" stroke-linecap="round" />
                    </svg>
                </div>
                <div class="modal-titlebar__text">Dados do usuário</div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label ops-label">Nome *</label>
                        <input type="text" name="name" class="form-control ops-input" value="<?= $h($user['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label ops-label">E-mail *</label>
                        <input type="email" name="email" class="form-control ops-input" value="<?= $h($user['email'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label ops-label">Tipo</label>
                        <select name="role" class="form-select ops-input">
                            <option value="user" <?= $role === 'user'    ? 'selected' : '' ?>>Usuário</option>
                            <!--<option value="analyst" <?= $role === 'analyst' ? 'selected' : '' ?>>Analista</option>
                            <option value="manager" <?= $role === 'manager' ? 'selected' : '' ?>>Gestor</option>-->
                            <option value="admin" <?= $role === 'admin'   ? 'selected' : '' ?>>Administrador</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label ops-label">Status</label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" id="u-active" name="active" <?= $active ? 'checked' : '' ?>>
                            <label class="form-check-label" for="u-active"><?= $active ? 'Ativo' : 'Inativo' ?></label>
                        </div>
                        <div class="form-text">Controle se o usuário pode acessar o sistema.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label ops-label"><?= $isEdit ? 'Trocar senha (opcional)' : 'Senha (opcional)' ?></label>
                        <div class="input-group">
                            <input type="password" id="pwd" name="password" class="form-control ops-input" placeholder="<?= $isEdit ? 'Deixe vazio para manter' : 'Mínimo 6 caracteres' ?>">
                            <button class="btn btn-outline-brand" type="button" id="togglePwd" aria-label="Mostrar/ocultar senha">👁</button>
                        </div>
                        <div class="form-text" id="pwdHint">Use letras maiúsculas, minúsculas e números.</div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-brand btn-pill"><?= $isEdit ? 'Salvar alterações' : 'Criar usuário' ?></button>
                <a href="/users" class="btn btn-brand btn-pill">Cancelar</a>
            </div>
        </div>
    </form>

    <!-- Card: Integração Microsoft -->
    <?php if ($isEdit): ?>
        <div class="card shadow-sm">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="modal-titlebar__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-microsoft" viewBox="0 0 16 16">
                            <path d="M7.462 0H0v7.19h7.462zM16 0H8.538v7.19H16zM7.462 8.211H0V16h7.462zm8.538 0H8.538V16H16z" />
                        </svg>
                    </div>
                    <div class="modal-titlebar__text">Integração Microsoft 365</div>
                    <?php if ($msConnected): ?>
                        <span class="ops-badge ops-badge--success ms-2">Conectado</span>
                    <?php else: ?>
                        <span class="ops-badge ops-badge--neutral ms-2">Não conectado</span>
                    <?php endif; ?>
                </div>

                <div class="d-flex gap-2">
                    <?php if ($msConnected): ?>
                        <a href="/auth/microsoft" class="btn btn-brand btn-pill">Reautenticar</a>
                        <a href="/auth/microsoft/unlink" class="btn btn-danger btn-pill"
                            onclick="return confirm('Deseja realmente desvincular a conta Microsoft?');">
                            Desconectar
                        </a>
                    <?php else: ?>
                        <a href="/auth/microsoft" class="btn btn-brand btn-pill">Conectar com Microsoft</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body">
                <?php if ($msConnected && $msToken): ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="text-muted small">Locatário (Tenant)</div>
                            <div class="fw-semibold"><?= $h($msToken['tenant_id'] ?? '-') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Expira em</div>
                            <div class="fw-semibold"><?= $h($msToken['expires_at'] ?? '-') ?></div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">Escopos</div>
                            <div class="fw-semibold text-break"><?= $h($msToken['scope'] ?? '-') ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="mb-0 text-muted">Vincule a conta Microsoft</p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info shadow-sm mt-3">
            Salve o usuário para habilitar a integração com a Microsoft.
        </div>
    <?php endif; ?>

</div>

<script>
    // Mostrar/ocultar senha + dica simples
    (function() {
        const pwd = document.getElementById('pwd');
        const btn = document.getElementById('togglePwd');
        const hint = document.getElementById('pwdHint');
        if (!pwd || !btn) return;

        btn.addEventListener('click', function() {
            const type = pwd.getAttribute('type') === 'password' ? 'text' : 'password';
            pwd.setAttribute('type', type);
            this.textContent = (type === 'password') ? '👁' : '🙈';
        });

        pwd.addEventListener('input', function() {
            const v = pwd.value || '';
            const okLen = v.length >= 6;
            const hasUpper = /[A-Z]/.test(v);
            const hasLower = /[a-z]/.test(v);
            const hasNum = /\d/.test(v);
            if (!v) {
                hint.textContent = 'Use letras maiúsculas, minúsculas e números.';
            } else if (okLen && hasUpper && hasLower && hasNum) {
                hint.textContent = 'Senha forte ✔';
            } else {
                hint.textContent = 'Dica: use ao menos 6 caracteres, incluindo maiúsculas, minúsculas e números.';
            }
        });
    })();
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
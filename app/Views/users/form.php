<?php include __DIR__ . '/../layout/header.php'; ?>

<?php
$isEdit = !empty($user);
$msConnected = $msConnected ?? false;
$msToken     = $msToken ?? null;
?>
<h2 class="mb-3"><?= $isEdit ? 'Editar usuário' : 'Novo usuário' ?></h2>

<form method="post" action="<?= $isEdit ? '/users/' . (int)$user['id'] : '/users' ?>" class="row g-3">

    <div class="col-md-6">
        <label class="form-label">Nome</label>
        <input type="text" name="name" class="form-control"
            value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
    </div>

    <div class="col-md-6">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" class="form-control"
            value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
    </div>

    <div class="col-md-4">
        <label class="form-label">Papel</label>
        <select name="role" class="form-select">
            <?php $role = $user['role'] ?? 'user'; ?>
            <option value="user" <?= $role === 'user'  ? 'selected' : '' ?>>Comum</option>
            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrador</option>
        </select>
    </div>

    <div class="col-md-4 d-flex align-items-end">
        <?php $active = isset($user['active']) ? (int)$user['active'] === 1 : true; ?>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="u-active" name="active" <?= $active ? 'checked' : '' ?>>
            <label class="form-check-label" for="u-active">Ativo</label>
        </div>
    </div>

    <div class="col-md-8">
        <label class="form-label"><?= $isEdit ? 'Trocar senha (opcional)' : 'Senha (opcional)' ?></label>
        <input type="password" name="password" class="form-control" placeholder="<?= $isEdit ? 'Deixe vazio para manter' : 'Opcional (mín. 6)' ?>">
    </div>

    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary"><?= $isEdit ? 'Salvar' : 'Criar' ?></button>
        <a class="btn btn-outline-secondary" href="/users">Voltar</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <hr class="my-4">

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Integração Microsoft 365</span>
            <?php if ($msConnected): ?>
                <span class="badge bg-success">Conectado</span>
            <?php else: ?>
                <span class="badge bg-secondary">Não conectado</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($msConnected && $msToken): ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Locatário (Tenant)</div>
                        <div class="fw-semibold"><?= htmlspecialchars($msToken['tenant_id'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Expira em</div>
                        <div class="fw-semibold">
                            <?= htmlspecialchars($msToken['expires_at'] ?? '-') ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Escopos</div>
                        <div class="fw-semibold" style="word-break: break-word;">
                            <?= htmlspecialchars($msToken['scope'] ?? '-') ?>
                        </div>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <a href="/auth/microsoft" class="btn btn-outline-primary">Reautenticar</a>
                    <a href="/auth/microsoft/unlink" class="btn btn-outline-danger"
                        onclick="return confirm('Deseja realmente desvincular a conta Microsoft?');">
                        Desconectar
                    </a>
                </div>
            <?php else: ?>
                <p class="mb-3">
                    Vincule a conta Microsoft para habilitar recursos como envio de e-mails e acesso ao Graph.
                </p>
                <a href="/auth/microsoft" class="btn btn-primary">Conectar com Microsoft</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info mt-4">
        Salve o usuário para habilitar a integração com a Microsoft.
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../layout/footer.php'; ?>
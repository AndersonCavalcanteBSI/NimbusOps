<?php include __DIR__ . '/../layout/header.php'; ?>

<?php $isEdit = !empty($user); ?>
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
            <?php
            $role = $user['role'] ?? 'user';
            ?>
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

<?php include __DIR__ . '/../layout/footer.php'; ?>
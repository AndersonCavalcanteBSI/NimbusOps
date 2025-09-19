<?php
$withNav = true;
$pageTitle = 'Usuários';
include __DIR__ . '/../layout/header.php';
?>

<h2 class="mb-3">Usuários</h2>

<?php if (!empty($ok)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="mb-2">
    <a class="btn btn-primary" href="/users/create">Novo usuário</a>
</div>

<div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead>
            <tr>
                <th>#</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Papel</th>
                <th>Ativo</th>
                <th>Último login</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($users ?? []) as $u): ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'secondary' ?>">
                            <?= htmlspecialchars($u['role']) ?></span></td>
                    <td><?= ((int)$u['active'] === 1 ? 'Sim' : 'Não') ?></td>
                    <td><?= htmlspecialchars($u['last_login_at'] ?? '-') ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="/users/<?= (int)$u['id'] ?>/edit">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
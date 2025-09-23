<?php
$withNav   = true;
$pageTitle = 'Usuários';
$pageCss   = ['/assets/ops.css']; // garante estilos do tema
include __DIR__ . '/../layout/header.php';

$h   = fn($v) => htmlspecialchars((string)$v);
$fmt = function (?string $d): string {
    if (!$d) return '-';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y H:i', $ts) : $h($d);
};

// Mapeia papéis para badges temáticas
$roleBadge = function (string $role): array {
    $r = mb_strtolower($role);
    return match ($r) {
        'admin', 'administrator', 'administrador' => ['Administrador', 'ops-badge--danger'],
        'manager', 'gestor'                        => ['Gestor', 'ops-badge--warning'],
        'analyst', 'analista'                      => ['Analista', 'ops-badge--info'],
        default                                    => [ucfirst($role ?: 'Usuário'), 'ops-badge--neutral'],
    };
};
?>

<!-- Hero compacto -->
<section class="ops-hero ops-hero--clean">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div class="ops-hero__stack">
                <h1 class="ops-hero__title">Usuários</h1>
                <p class="ops-hero__subtitle">Gerencie acessos, papéis e status de login</p>
            </div>
            <a class="btn btn-brand btn-pill" href="/users/create">+ Novo usuário</a>
        </div>
    </div>
</section>

<div class="container my-4">

    <?php if (!empty($ok)): ?>
        <div class="alert alert-success shadow-sm"><?= $h($ok) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger shadow-sm"><?= $h($error) ?></div>
    <?php endif; ?>

    <!-- (Opcional) Filtros simples: funcionam se o controller aceitar ?q=&role=&active= -->
    <form class="card ops-filter shadow-sm mb-3" method="get">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-6">
                    <label class="form-label ops-label">Buscar</label>
                    <input type="text" name="q" value="<?= $h($_GET['q'] ?? '') ?>" class="form-control ops-input" placeholder="Nome ou e-mail">
                </div>
                <div class="col-lg-3">
                    <label class="form-label ops-label">Papel</label>
                    <select name="role" class="form-select ops-input">
                        <option value="">Todos</option>
                        <?php foreach (['admin' => 'Administrador', 'manager' => 'Gestor', 'analyst' => 'Analista', 'user' => 'Usuário'] as $val => $lab): ?>
                            <option value="<?= $val ?>" <?= (($s = $_GET['role'] ?? '') === $val ? 'selected' : '') ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label ops-label">Ativo</label>
                    <select name="active" class="form-select ops-input">
                        <option value="">Todos</option>
                        <option value="1" <?= (($_GET['active'] ?? '') === '1' ? 'selected' : '') ?>>Sim</option>
                        <option value="0" <?= (($_GET['active'] ?? '') === '0' ? 'selected' : '') ?>>Não</option>
                    </select>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-brand">Filtrar</button>
                <a class="btn btn-outline-brand" href="/users">Limpar</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle ops-table mb-0">
                <thead class="ops-thead">
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th style="width: 16ch">Papel</th>
                        <th style="width: 10ch">Ativo</th>
                        <th style="width: 22ch">Último login</th>
                        <th class="text-end" style="width: 10ch">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <div class="empty-state">
                                    <div class="empty-state__title">Nenhum usuário encontrado</div>
                                    <div class="empty-state__desc">Ajuste os filtros ou cadastre um novo usuário.</div>
                                </div>
                            </td>
                        </tr>
                        <?php else: foreach (($users ?? []) as $u): ?>
                            <?php [$roleLabel, $roleCls] = $roleBadge($u['role'] ?? ''); ?>
                            <tr>
                                <td class="fw-semibold"><?= $h($u['name'] ?? '-') ?></td>
                                <td class="text-break"><?= $h($u['email'] ?? '-') ?></td>
                                <td>
                                    <span class="ops-badge <?= $roleCls ?>"><?= $h($roleLabel) ?></span>
                                </td>
                                <td>
                                    <?php if ((int)($u['active'] ?? 0) === 1): ?>
                                        <span class="ops-badge ops-badge--success">Sim</span>
                                    <?php else: ?>
                                        <span class="ops-badge ops-badge--danger">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $fmt($u['last_login_at'] ?? null) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-brand" href="/users/<?= (int)($u['id'] ?? 0) ?>/edit">Editar</a>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
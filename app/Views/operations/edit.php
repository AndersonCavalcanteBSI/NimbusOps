<?php
$withNav   = true;
$pageTitle = 'Editar Operação';
$pageCss   = ['/assets/ops.css'];
include __DIR__ . '/../layout/header.php';

// Helper para value "old" (em caso de erro de validação)
$old = $_SESSION['form_old']    ?? [];
$err = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_old'], $_SESSION['form_errors']);

$val = function (string $k, $default = '') use ($op, $old) {
    if (array_key_exists($k, $old))  return htmlspecialchars((string)$old[$k]);
    return htmlspecialchars((string)($op[$k] ?? $default));
};

$fmtDate = function (?string $d): string {
    if (!$d) return '';
    $ts = strtotime($d);
    return $ts ? date('Y-m-d', $ts) : '';
};

// Lista de status válidos (coerente com seu fluxo)
$statuses = ['Engenharia', 'Gestão', 'Jurídico', 'Pagamento', 'Finalizar', 'Completo', 'Rejeitado'];

// Se não vier lista de usuários, cria fallback vazio
$users = $users ?? [];
?>
<section class="container my-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">Editar Operação</h1>
        <a href="/operations/<?= (int)$op['id'] ?>" class="btn btn-outline-secondary">Voltar</a>
    </div>

    <?php if ($err): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($err as $e): ?>
                    <li><?= htmlspecialchars((string)$e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <form method="post" action="/operations/<?= (int)$op['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf) ?>">

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Título *</label>
                        <input type="text" name="title" class="form-control" value="<?= $val('title') ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Código</label>
                        <input type="text" name="code" class="form-control" value="<?= $val('code') ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-select" required>
                            <option value="">Selecione</option>
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>" <?= ($val('status') === $s ? 'selected' : '') ?>>
                                    <?= htmlspecialchars($s) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Responsável (1ª etapa)</label>
                        <select name="responsible_user_id" class="form-select">
                            <option value="0">—</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= ((int)$val('responsible_user_id', (int)($op['responsible_user_id'] ?? 0)) === (int)$u['id'] ? 'selected' : '') ?>>
                                    <?= htmlspecialchars((string)$u['name']) ?> (<?= htmlspecialchars((string)$u['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Revisor 2ª etapa (Gestão)</label>
                        <select name="stage2_reviewer_user_id" class="form-select">
                            <option value="0">—</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= ((int)$val('stage2_reviewer_user_id', (int)($op['stage2_reviewer_user_id'] ?? 0)) === (int)$u['id'] ? 'selected' : '') ?>>
                                    <?= htmlspecialchars((string)$u['name']) ?> (<?= htmlspecialchars((string)$u['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Revisor 3ª etapa (Jurídico)</label>
                        <select name="stage3_reviewer_user_id" class="form-select">
                            <option value="0">—</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= ((int)$val('stage3_reviewer_user_id', (int)($op['stage3_reviewer_user_id'] ?? 0)) === (int)$u['id'] ? 'selected' : '') ?>>
                                    <?= htmlspecialchars((string)$u['name']) ?> (<?= htmlspecialchars((string)$u['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Gestor Pagamento (4ª etapa)</label>
                        <select name="payment_manager_user_id" class="form-select">
                            <option value="0">—</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= ((int)$val('payment_manager_user_id', (int)($op['payment_manager_user_id'] ?? 0)) === (int)$u['id'] ? 'selected' : '') ?>>
                                    <?= htmlspecialchars((string)$u['name']) ?> (<?= htmlspecialchars((string)$u['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Finalizador (pós-4ª etapa)</label>
                        <select name="payment_finalizer_user_id" class="form-select">
                            <option value="0">—</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= ((int)$val('payment_finalizer_user_id', (int)($op['payment_finalizer_user_id'] ?? 0)) === (int)$u['id'] ? 'selected' : '') ?>>
                                    <?= htmlspecialchars((string)$u['name']) ?> (<?= htmlspecialchars((string)$u['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Próxima medição (YYYY-MM-DD)</label>
                        <input type="date" name="next_measurement_at" class="form-control" value="<?= htmlspecialchars($fmtDate($op['next_measurement_at'] ?? null)) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-brand">Salvar alterações</button>
            <a href="/operations/<?= (int)$op['id'] ?>" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</section>

<?php include __DIR__ . '/../layout/footer.php'; ?>
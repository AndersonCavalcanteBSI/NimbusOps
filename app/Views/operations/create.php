<?php
$withNav = true;
$pageTitle = 'Criar Nova Operação';
include __DIR__ . '/../layout/header.php';
?>
<a href="/operations" class="btn btn-link">← Voltar</a>
<h2 class="mb-3">Nova Operação</h2>

<form class="row g-3" method="post" action="/operations">
    <div class="col-md-3">
        <label class="form-label">Código *</label>
        <input type="text" name="code" class="form-control" required>
    </div>
    <div class="col-md-5">
        <label class="form-label">Título *</label>
        <input type="text" name="title" class="form-control" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Emissor</label>
        <input type="text" name="issuer" class="form-control">
    </div>

    <div class="col-md-3">
        <label class="form-label">Vencimento</label>
        <input type="date" name="due_date" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">Valor</label>
        <input type="number" step="0.01" min="0" name="amount" class="form-control">
    </div>

    <hr class="mt-3">

    <?php
    /** @var array<int,array{id:int,name:string,email:string}> $users */
    $opts = function (array $users) {
        echo '<option value="">—</option>';
        foreach ($users as $u) {
            $label = htmlspecialchars($u['name'] . ' <' . $u['email'] . '>');
            echo '<option value="' . (int)$u['id'] . '">' . $label . '</option>';
        }
    };
    ?>

    <div class="card mt-3">
        <div class="card-header">Notificações por Fase</div>
        <div class="card-body row g-3">
            <?php
            $sel = fn($v, $id) => ((int)($v ?? 0) === (int)$id ? 'selected' : '');
            $op  = $op ?? [];
            ?>

            <div class="col-md-6">
                <label class="form-label">1ª validação — Responsável</label>
                <select class="form-select" name="responsible_user_id">
                    <option value="">— Selecionar —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $sel($op['responsible_user_id'] ?? null, $u['id']) ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">2ª validação — Revisor</label>
                <select class="form-select" name="stage2_reviewer_user_id">
                    <option value="">— Selecionar —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $sel($op['stage2_reviewer_user_id'] ?? null, $u['id']) ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">3ª validação — Revisor</label>
                <select class="form-select" name="stage3_reviewer_user_id">
                    <option value="">— Selecionar —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $sel($op['stage3_reviewer_user_id'] ?? null, $u['id']) ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Pagamentos — Gestor (4ª etapa)</label>
                <select class="form-select" name="payment_manager_user_id">
                    <option value="">— Selecionar —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $sel($op['payment_manager_user_id'] ?? null, $u['id']) ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!--<div class="col-md-6">
                <label class="form-label">Reprovação — Notificar</label>
                <select class="form-select" name="rejection_notify_user_id">
                    <option value="">— Selecionar —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $sel($op['rejection_notify_user_id'] ?? null, $u['id']) ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>-->

            <div class="col-md-6">
                <label class="form-label">Notificar em caso de reprovação</label>
                <select name="rejection_notify_user_ids[]" class="form-select" multiple size="6">
                    <option value="">— Selecionar —</option>
                    <?php foreach (($users ?? []) as $u): ?>
                        <option value="<?= (int)$u['id'] ?>">
                            <?= htmlspecialchars($u['name']) ?> — <?= htmlspecialchars($u['email']) ?>
                        </option>
                    <?php endforeach; ?>
                    </option>
                </select>
                <div class="form-text">Segure Ctrl (Windows) ou Cmd (macOS) para selecionar dois usuários.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Finalização — Responsável (após pagamentos)</label>
                <select class="form-select" name="payment_finalizer_user_id">
                    <option value="">— Selecionar —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $sel($op['payment_finalizer_user_id'] ?? null, $u['id']) ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="col-12 d-flex gap-2 mt-2">
        <button class="btn btn-primary">Criar</button>
        <a class="btn btn-outline-secondary" href="/operations">Cancelar</a>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sel = document.querySelector('select[name="rejection_notify_user_ids[]"]');
        if (!sel) return;
        sel.addEventListener('change', function() {
            const selected = Array.from(this.options).filter(o => o.selected);
            if (selected.length > 2) {
                // desmarca o primeiro selecionado para manter só 2
                selected[0].selected = false;
            }
        });
    });
</script>


<?php include __DIR__ . '/../layout/footer.php'; ?>
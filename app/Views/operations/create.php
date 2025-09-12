<?php include __DIR__ . '/../layout/header.php'; ?>
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

    <div class="col-md-6">
        <label class="form-label">Responsável (1ª validação)</label>
        <select name="responsible_user_id" class="form-select"><?php $opts($users ?? []); ?></select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Revisor da 2ª validação</label>
        <select name="stage2_reviewer_user_id" class="form-select"><?php $opts($users ?? []); ?></select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Revisor da 3ª validação</label>
        <select name="stage3_reviewer_user_id" class="form-select"><?php $opts($users ?? []); ?></select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Gestor de Pagamentos (4ª etapa)</label>
        <select name="payment_manager_user_id" class="form-select"><?php $opts($users ?? []); ?></select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Finalizador do Pagamento</label>
        <select name="payment_finalizer_user_id" class="form-select"><?php $opts($users ?? []); ?></select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Notificar em caso de Recusa</label>
        <select name="rejection_notify_user_id" class="form-select"><?php $opts($users ?? []); ?></select>
    </div>

    <div class="col-12 d-flex gap-2 mt-2">
        <button class="btn btn-primary">Criar</button>
        <a class="btn btn-outline-secondary" href="/operations">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../layout/footer.php'; ?>
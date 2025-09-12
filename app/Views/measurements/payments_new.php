<?php include __DIR__ . '/../layout/header.php'; ?>

<a href="/operations/<?= (int)$file['op_id'] ?>" class="btn btn-link">← Voltar para a operação</a>
<h2 class="mb-3">Registrar Pagamentos — Operação #<?= (int)$file['op_id'] ?> (<?= htmlspecialchars($file['op_title']) ?>)</h2>

<?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">
        Pagamentos registrados com sucesso. <strong>Lembre-se de adicionar o pagamento no banco.</strong>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        Esta medição foi aprovada nas 3 validações. Informe os pagamentos abaixo.
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <div><span class="text-muted small">Arquivo</span> <strong><?= htmlspecialchars($file['filename']) ?></strong></div>
        <?php if (!empty($file['storage_path'])): ?>
            <div class="mt-1"><a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($file['storage_path']) ?>" target="_blank">Baixar / Abrir</a></div>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="/measurements/<?= (int)$file['id'] ?>/payments" class="mb-4">
    <div id="rows">
        <div class="row g-2 align-items-end mb-2">
            <div class="col-md-3">
                <label class="form-label">Data</label>
                <input type="date" class="form-control" name="pay_date[]" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Valor</label>
                <input type="number" step="0.01" min="0" class="form-control" name="amount[]" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Forma</label>
                <input type="text" class="form-control" name="method[]" placeholder="TED, PIX, Boleto...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Observações</label>
                <input type="text" class="form-control" name="notes[]" placeholder="Opcional">
            </div>
        </div>
    </div>

    <button type="button" class="btn btn-outline-secondary mb-3" onclick="addRow()">Adicionar outro pagamento</button>
    <div class="d-flex gap-2">
        <button class="btn btn-primary">Salvar pagamentos</button>
        <a href="/operations/<?= (int)$file['op_id'] ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php if (!empty($payments)): ?>
    <div class="card">
        <div class="card-header">Pagamentos já registrados</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th class="text-end">Valor</th>
                        <th>Método</th>
                        <th>Observações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['pay_date']) ?></td>
                            <td class="text-end">R$ <?= number_format((float)$p['amount'], 2, ',', '.') ?></td>
                            <td><?= htmlspecialchars($p['method'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['notes']  ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
    function addRow() {
        const rows = document.getElementById('rows');
        const div = document.createElement('div');
        div.className = 'row g-2 align-items-end mb-2';
        div.innerHTML = `
    <div class="col-md-3">
      <input type="date" class="form-control" name="pay_date[]" required>
    </div>
    <div class="col-md-3">
      <input type="number" step="0.01" min="0" class="form-control" name="amount[]" required>
    </div>
    <div class="col-md-3">
      <input type="text" class="form-control" name="method[]" placeholder="TED, PIX, Boleto...">
    </div>
    <div class="col-md-3">
      <input type="text" class="form-control" name="notes[]" placeholder="Opcional">
    </div>`;
        rows.appendChild(div);
    }
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
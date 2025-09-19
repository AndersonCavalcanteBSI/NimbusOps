<?php
$withNav = true;
$pageTitle = 'Finalização da Medição - Operação ' . $file['op_title'];
include __DIR__ . '/../layout/header.php';
?>

<a href="/operations/<?= (int)$operationId ?>" class="btn btn-link">← Voltar</a>
<h2 class="mb-3">Finalizar Pagamento — Operação: <?= $file['op_title'] ?></h2>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <div class="text-muted small">Arquivo</div>
                <div class="fw-semibold"><?= htmlspecialchars($file['filename'] ?? '') ?></div>
                <?php if (!empty($file['storage_path'])): ?>
                    <div class="mt-1"><a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($file['storage_path']) ?>" target="_blank">Baixar / Abrir</a></div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Enviado em</div>
                <div class="fw-semibold"><?= htmlspecialchars($file['uploaded_at'] ?? '-') ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($reviews)): ?>
    <div class="card mb-3">
        <div class="card-header">Histórico de Análises</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Etapa</th>
                        <th>Status</th>
                        <th>Revisor</th>
                        <th>Quando</th>
                        <th>Observações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td><?= (int)$r['stage'] ?>ª</td>
                            <td><?= htmlspecialchars($r['status']) ?></td>
                            <td><?= htmlspecialchars($r['reviewer_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['reviewed_at'] ?? '-') ?></td>
                            <td><?= nl2br(htmlspecialchars($r['notes'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header">Pagamentos</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Método</th>
                    <th>Observações</th>
                    <th class="text-end">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $total = 0.0;
                foreach (($payments ?? []) as $p):
                    $total += (float)$p['amount'];
                ?>
                    <tr>
                        <td><?= htmlspecialchars($p['pay_date']) ?></td>
                        <td><?= htmlspecialchars($p['method'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['notes']  ?? '-') ?></td>
                        <td class="text-end">R$ <?= number_format((float)$p['amount'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3" class="text-end"><strong>Total</strong></td>
                    <td class="text-end"><strong>R$ <?= number_format((float)$total, 2, ',', '.') ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<form method="post" action="/measurements/<?= (int)$file['id'] ?>/finalize" class="mt-3">
    <div class="alert alert-warning">
        Ao confirmar, o status da operação será atualizado para <strong>Concluído</strong>.
    </div>
    <button class="btn btn-success">Confirmar finalização</button>
    <a class="btn btn-outline-secondary" href="/operations/<?= (int)$operationId ?>">Cancelar</a>
</form>

<?php include __DIR__ . '/../layout/footer.php'; ?>
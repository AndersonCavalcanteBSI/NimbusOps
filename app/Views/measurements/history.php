<?php include __DIR__ . '/../layout/header.php'; ?>

<a href="/operations/<?= (int)$operationId ?>" class="btn btn-link">← Voltar</a>
<h2 class="mb-3">Histórico da Medição</h2>

<?php if (!empty($file['status'])): ?>
    <div class="alert alert-<?= $file['status'] === 'Concluído' ? 'success' : 'secondary' ?>">
        <strong>Status:</strong> <?= htmlspecialchars($file['status']) ?>
        <?php if (!empty($file['closed_at'])): ?> • Finalizada em <?= htmlspecialchars($file['closed_at']) ?><?php endif; ?>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-muted small">Arquivo</div>
                <div class="fw-semibold"><?= htmlspecialchars($file['filename'] ?? '-') ?></div>
                <?php if (!empty($file['storage_path'])): ?>
                    <div class="mt-1">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($file['storage_path']) ?>" target="_blank">Baixar / Abrir</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Operação</div>
                <div class="fw-semibold">#<?= (int)$file['op_id'] ?> — <?= htmlspecialchars($file['op_title'] ?? '-') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Enviado em</div>
                <div class="fw-semibold"><?= htmlspecialchars($file['uploaded_at'] ?? '-') ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($reviews)): ?>
    <div class="card mb-3">
        <div class="card-header">Análises / Validações</div>
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
                            <td><?= (int)($r['stage'] ?? 0) ?>ª</td>
                            <td><?= htmlspecialchars($r['status'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['reviewer_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['reviewed_at'] ?? '-') ?></td>
                            <td><?= nl2br(htmlspecialchars($r['notes'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($payments)): ?>
    <div class="card mb-3">
        <div class="card-header">Pagamentos</div>
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
                    <?php $total = 0.0;
                    foreach ($payments as $p): $total += (float)$p['amount']; ?>
                        <tr>
                            <td><?= htmlspecialchars($p['pay_date']) ?></td>
                            <td class="text-end">R$ <?= number_format((float)$p['amount'], 2, ',', '.') ?></td>
                            <td><?= htmlspecialchars($p['method'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['notes']  ?? '-') ?></td>
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
<?php endif; ?>

<?php if (empty($reviews) && empty($payments)): ?>
    <div class="alert alert-secondary">Sem registros para esta medição.</div>
<?php endif; ?>

<?php include __DIR__ . '/../layout/footer.php'; ?>
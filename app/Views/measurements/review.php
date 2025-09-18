<?php include __DIR__ . '/../layout/header.php'; ?>

<?php $isReadOnly = !empty($readOnly); ?>

<a href="/operations/<?= (int)$operationId ?>" class="btn btn-link">← Voltar</a>
<h2 class="mb-3">Análise da Medição — <?= (int)$stage ?>ª validação</h2>

<?php if ($isReadOnly): ?>
    <div class="alert alert-success mb-3">
        <strong>Medição finalizada.</strong> Esta tela está em modo <em>somente leitura</em> (status da operação: <strong>Completo</strong>).
        Você pode consultar o histórico abaixo, mas não é possível registrar novas ações.
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-muted small">Arquivo</div>
                <div class="fw-semibold"><?= htmlspecialchars($file['filename']) ?></div>
                <?php if (!empty($file['storage_path'])): ?>
                    <div class="mt-1">
                        <a class="btn btn-sm btn-outline-secondary"
                            href="<?= htmlspecialchars($file['storage_path']) ?>" target="_blank">
                            Baixar / Abrir
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Enviado em</div>
                <div class="fw-semibold"><?= htmlspecialchars($file['uploaded_at'] ?? '-') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Etapa</div>
                <div class="fw-semibold"><?= (int)$stage ?>ª validação</div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($previous) && $stage > 1): ?>
    <div class="card mb-3">
        <div class="card-header">Observações anteriores</div>
        <div class="card-body">
            <ul class="list-group list-group-flush">
                <?php foreach ($previous as $pr): if ((int)$pr['stage'] >= (int)$stage) continue; ?>
                    <li class="list-group-item">
                        <div class="small text-muted mb-1">
                            <?= (int)$pr['stage'] ?>ª validação —
                            <?= htmlspecialchars($pr['reviewer_name'] ?? 'Revisor') ?>
                            <?php if (!empty($pr['reviewed_at'])): ?> • <?= htmlspecialchars($pr['reviewed_at']) ?><?php endif; ?>
                        </div>
                        <div><strong>Status:</strong> <?= htmlspecialchars($pr['status']) ?></div>
                        <?php if (!empty($pr['notes'])): ?>
                            <div class="mt-1"><?= nl2br(htmlspecialchars($pr['notes'])) ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php if ((int)$stage === 4): ?>
    <?php if ($isReadOnly): ?>
        <div class="alert alert-info">
            Esta é a <strong>4ª validação</strong> (gestão de pagamentos). A medição está finalizada;
            os pagamentos registrados estão listados abaixo para consulta.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Esta é a <strong>4ª validação</strong> (gestão de pagamentos).
            Registre os pagamentos e depois retorne aqui para <em>aprovar</em> esta etapa.
        </div>

        <p>
            <a class="btn btn-outline-primary" href="/measurements/<?= (int)$file['id'] ?>/payments/new" target="_blank">
                Registrar/Editar Pagamentos
            </a>
        </p>
    <?php endif; ?>

    <?php if (!empty($payments)): ?>
        <div class="card mb-3">
            <div class="card-header">Pagamentos já registrados</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th class="text-end">Valor</th>
                            <th>Método</th>
                            <th>Obs.</th>
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
<?php endif; ?>

<?php if (!$isReadOnly): ?>
    <form method="post" action="/measurements/<?= (int)$file['id'] ?>/review/<?= (int)$stage ?>">
        <div class="mb-3">
            <label class="form-label">Observações</label>
            <textarea class="form-control" name="notes" rows="4" required></textarea>
        </div>
        <div class="d-flex gap-2">
            <button name="decision" value="approve" class="btn btn-success">Aprovar</button>
            <button name="decision" value="reject" class="btn btn-danger">Reprovar</button>
        </div>
    </form>
<?php endif; ?>

<?php include __DIR__ . '/../layout/footer.php'; ?>
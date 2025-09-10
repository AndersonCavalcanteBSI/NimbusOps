<?php include __DIR__ . '/../layout/header.php'; ?>
<a href="/operations" class="btn btn-link">← Voltar</a>
<h2 class="mb-3">Operação #<?= (int)$op['id'] ?> — <?= htmlspecialchars($op['title']) ?></h2>


<div class="row g-3">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Código</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($op['code']) ?></dd>
                    <dt class="col-sm-3">Status</dt>
                    <dd class="col-sm-9"><span class="badge text-bg-primary"><?= ucfirst($op['status']) ?></span></dd>
                    <dt class="col-sm-3">Emissor</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($op['issuer'] ?? '-') ?></dd>
                    <dt class="col-sm-3">Vencimento</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($op['due_date'] ?? '-') ?></dd>
                    <dt class="col-sm-3">Valor</dt>
                    <dd class="col-sm-9">R$ <?= number_format((float)$op['amount'], 2, ',', '.') ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">Histórico de alterações</div>
            <div class="card-body">
                <?php if (!$history): ?>
                    <p class="text-muted">Sem histórico.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($history as $h): ?>
                            <li class="list-group-item">
                                <div class="small text-muted"><?= htmlspecialchars($h['created_at']) ?></div>
                                <strong><?= htmlspecialchars($h['action']) ?></strong>
                                <div><?= nl2br(htmlspecialchars($h['notes'])) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../layout/footer.php'; ?>
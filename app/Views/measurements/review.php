<?php include __DIR__ . '/../layout/header.php'; ?>

<a href="/operations/<?= (int)$operationId ?>" class="btn btn-link">← Voltar</a>
<h2 class="mb-3">Análise da Medição</h2>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-muted small">Arquivo</div>
                <div class="fw-semibold"><?= htmlspecialchars($file['filename']) ?></div>
                <?php if (!empty($file['storage_path'])): ?>
                    <div class="mt-1"><a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($file['storage_path']) ?>" target="_blank">Baixar / Abrir</a></div>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Enviado em</div>
                <div class="fw-semibold"><?= htmlspecialchars($file['uploaded_at'] ?? '-') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Etapa</div>
                <div class="fw-semibold">1ª validação</div>
            </div>
        </div>
    </div>
</div>

<form method="post" action="/measurements/<?= (int)$file['id'] ?>/review">
    <div class="mb-3">
        <label class="form-label">Observações</label>
        <textarea class="form-control" name="notes" rows="4" required></textarea>
    </div>
    <div class="d-flex gap-2">
        <button name="decision" value="approve" class="btn btn-success">Aprovar</button>
        <button name="decision" value="reject" class="btn btn-danger">Reprovar</button>
    </div>
</form>

<?php include __DIR__ . '/../layout/footer.php'; ?>
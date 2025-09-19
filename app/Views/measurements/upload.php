<?php
$withNav = true;
$pageTitle = 'Upload de Medição';
include __DIR__ . '/../layout/header.php';
?>

<h2 class="mb-3">Upload de Medição</h2>
<?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">Arquivo enviado, status atualizado para <strong>Pendente</strong> e responsável notificado.</div>
<?php endif; ?>

<form class="row g-3" method="post" action="/measurements/upload" enctype="multipart/form-data">
    <div class="col-md-6">
        <label class="form-label">Operação</label>
        <select name="operation_id" class="form-select" required>
            <option value="">Selecione…</option>
            <?php foreach (($operations ?? []) as $o): ?>
                <option value="<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?> — <?= htmlspecialchars($o['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Arquivo de medição</label>
        <input type="file" name="file" class="form-control" accept=".pdf,.xlsx,.xls,.csv" required>
        <div class="form-text">Tipos aceitos: PDF, XLSX, XLS, CSV. Tamanho máximo: 20MB.</div>
    </div>
    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary">Enviar</button>
        <a class="btn btn-outline-secondary" href="/operations">Voltar</a>
    </div>
</form>

<?php include __DIR__ . '/../layout/footer.php'; ?>
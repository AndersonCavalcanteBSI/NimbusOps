<?php include __DIR__ . '/../layout/header.php'; ?>

<a href="/operations" class="btn btn-link">← Voltar</a>
<h2 class="mb-3">Operação #<?= (int)$op['id'] ?> — <?= htmlspecialchars($op['title']) ?></h2>

<?php
$fmt = function (?string $d): string {
    if (!$d) return '-';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : '-';
};
?>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-md-3">
                <div class="text-muted small">Nome</div>
                <div class="fw-semibold"><?= htmlspecialchars($op['title']) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Status</div>
                <span class="badge text-bg-<?= ($displayStatus === 'Em aberto')
                                                ? 'warning'
                                                : ($op['status'] === 'active' ? 'success' : ($op['status'] === 'draft' ? 'secondary' : ($op['status'] === 'settled' ? 'info' : 'danger'))) ?>">
                    <?= htmlspecialchars($displayStatus) ?>
                </span>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Próxima medição</div>
                <div class="fw-semibold"><?= $fmt($op['next_measurement_at'] ?? null) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Última medição</div>
                <div class="fw-semibold"><?= $fmt($op['last_measurement_at'] ?? null) ?></div>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#opHistoryModal">
                Histórico da operação
            </button>
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filesModal">
                Arquivos de medição
            </button>
        </div>
    </div>
</div>

<!-- Modal: Histórico da operação -->
<div class="modal fade" id="opHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Histórico da operação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <?php if (!$history): ?>
                    <p class="text-muted mb-0">Sem histórico.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($history as $h): ?>
                            <li class="list-group-item">
                                <!--<div class="small text-muted"><?= htmlspecialchars($h['created_at']) ?></div>-->
                                <div class="small text-muted">
                                    <?= htmlspecialchars($h['created_at']) ?><?= !empty($h['user_name']) ? ' • por ' . htmlspecialchars($h['user_name']) : '' ?>
                                </div>
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

<!-- Modal: Arquivos de medição (com histórico por arquivo) -->
<div class="modal fade" id="filesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Arquivos de medição</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <?php if (!$files): ?>
                    <p class="text-muted mb-0">Nenhum arquivo de medição enviado.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Arquivo</th>
                                    <th>Enviado em</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $f): ?>
                                    <?php
                                    $histList   = $filesHistory[$f['id']] ?? [];
                                    $fileStatus = (string)($f['file_status'] ?? '');
                                    $isDone     = (mb_strtolower($fileStatus, 'UTF-8') === mb_strtolower('Concluído', 'UTF-8'));
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($f['filename']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($f['storage_path']) ?></div>
                                        </td>
                                        <td><?= $fmt($f['uploaded_at'] ?? null) ?></td>
                                        <td>
                                            <?php if ($isDone): ?>
                                                <span class="badge text-bg-success">Concluído</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-warning">Pendente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end d-flex gap-2 justify-content-end">
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($f['history_url']) ?>">
                                                Ver histórico
                                            </a>
                                            <?php if (!empty($f['can_review']) && !$isDone): ?>
                                                <a class="btn btn-sm btn-primary"
                                                    href="/measurements/<?= (int)$f['id'] ?>/review/<?= (int)($f['next_stage'] ?? 1) ?>">
                                                    Analisar<?= isset($f['next_stage']) ? ' (' . (int)$f['next_stage'] . 'ª)' : '' ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
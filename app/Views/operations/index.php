<?php
$withNav   = true;
$pageTitle = 'Operações';
$pageCss   = ['/assets/ops.css']; // CSS da página
include __DIR__ . '/../layout/header.php';
?>

<section class="ops-hero ops-hero--plain ops-hero--compact">
    <div class="container d-flex align-items-center justify-content-between">
        <div>
            <h1 class="ops-hero__title mb-0">Operações</h1>
            <p class="ops-hero__subtitle">Acompanhe o status das operações e o calendário de medições</p>
        </div>
        <?php if ($role === 'admin'): ?>
            <a href="/operations/create" class="btn btn-outline-brand d-inline-flex align-items-center gap-2 mb-3">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                Nova operação
            </a>
        <?php endif; ?>
    </div>
</section>

<div class="container my-4">
    <!-- Filtros -->
    <form class="card ops-filter shadow-sm mb-4" method="get">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-6">
                    <label class="form-label ops-label">Buscar</label>
                    <input type="text" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" class="form-control ops-input" placeholder="Nome, código ou termos da operação">
                </div>

                <div class="col-lg-2">
                    <label class="form-label ops-label">Status</label>
                    <select name="status" class="form-select ops-input">
                        <option value="">Todos</option>
                        <?php foreach (['draft', 'active', 'settled', 'canceled', 'pending', 'engenharia', 'gestão', 'jurídico', 'pagamento', 'finalização', 'completo'] as $s): ?>
                            <option value="<?= $s ?>" <?= (($filters['status'] ?? '') === $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2">
                    <label class="form-label ops-label">De</label>
                    <input type="date" name="from" value="<?= htmlspecialchars($filters['from'] ?? '') ?>" class="form-control ops-input">
                </div>

                <div class="col-lg-2">
                    <label class="form-label ops-label">Até</label>
                    <div class="d-flex gap-2">
                        <input type="date" name="to" value="<?= htmlspecialchars($filters['to'] ?? '') ?>" class="form-control ops-input">
                    </div>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-brand">Filtrar</button>
                <a class="btn btn-outline-brand" href="/operations">Limpar</a>
            </div>
        </div>
    </form>

    <?php
    $data  = $result['data'] ?? [];
    $page  = $result['page'] ?? 1;
    $per   = $result['per_page'] ?? 10;
    $total = $result['total'] ?? 0;

    $pages  = max(1, (int)ceil($total / $per));
    $invert = ($dir === 'asc') ? 'desc' : 'asc';
    $qs = fn($o) => http_build_query(array_merge($_GET, ['order' => $o, 'dir' => $invert, 'page' => 1]));

    $fmt = function (?string $d): string {
        if (!$d) return '-';
        $ts = strtotime($d);
        return $ts ? date('d/m/Y', $ts) : '-';
    };
    ?>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table ops-table align-middle mb-0">
                <thead class="ops-thead">
                    <tr>
                        <th><a class="ops-th-link" href="?<?= $qs('title') ?>">Nome</a></th>
                        <th class="d-none d-md-table-cell"><a class="ops-th-link" href="?<?= $qs('status') ?>">Status</a></th>
                        <th><a class="ops-th-link" href="?<?= $qs('due_date') ?>">Próxima medição</a></th>
                        <th class="d-none d-lg-table-cell"><a class="ops-th-link" href="?<?= $qs('last_measurement_at') ?>">Última medição</a></th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$data): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <div class="empty-state">
                                    <div class="empty-state__title">Nenhuma operação encontrada</div>
                                    <div class="empty-state__desc">Ajuste os filtros ou limpe a busca.</div>
                                </div>
                            </td>
                        </tr>
                        <?php else: foreach ($data as $row): ?>
                            <?php
                            // Fallback badges (status sistêmico)
                            $sysBadge = match ($row['status']) {
                                'active'  => 'ops-badge--success',
                                'draft'   => 'ops-badge--neutral',
                                'settled' => 'ops-badge--info',
                                'pending' => 'ops-badge--warning',
                                'canceled' => 'ops-badge--danger',
                                default   => 'ops-badge--danger',
                            };

                            // Mapa de estágios do pipeline -> classe de cor
                            $stageMap = [
                                'Engenharia'  => 'stage--engenharia',
                                'Gestão'      => 'stage--gestao',
                                'Jurídico'    => 'stage--juridico',
                                'Pagamento'   => 'stage--pagamento',
                                'Finalização' => 'stage--finalizar',
                                'Completo'    => 'stage--completo',
                            ];
                            $statusLabel = (string)($row['status'] ?? '');
                            $stageClass  = $stageMap[$statusLabel] ?? null;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark mb-0"><?= htmlspecialchars($row['title']) ?></div>
                                    <?php if (!empty($row['code'])): ?>
                                        <div class="small text-muted">Código: <?= htmlspecialchars($row['code']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td class="d-none d-md-table-cell">
                                    <?php if ($stageClass): ?>
                                        <span class="stage-badge <?= $stageClass ?>">
                                            <?= htmlspecialchars($statusLabel) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="ops-badge <?= $sysBadge ?>"><?= htmlspecialchars(ucfirst($statusLabel)) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td><?= $fmt($row['due_date'] ?? null) ?></td>

                                <td class="d-none d-lg-table-cell"><?= $fmt($row['last_measurement_at'] ?? null) ?></td>

                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-brand" href="/operations/<?= (int)$row['id'] ?>">Abrir</a>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginação -->
    <?php if ($pages > 1): ?>
        <nav aria-label="Paginação" class="mt-3">
            <ul class="pagination justify-content-end ops-pagination">
                <?php for ($p = 1; $p <= $pages; $p++): $q = http_build_query(array_merge($_GET, ['page' => $p])); ?>
                    <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= $q ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
<?php
$withNav   = true;
$pageTitle = 'Finalização da Medição - ' . ($file['op_title'] ?? 'Operação');
$pageCss   = ['/assets/ops.css'];
include __DIR__ . '/../layout/header.php';

$fmt = function (?string $d, bool $withTime = false): string {
    if (!$d) return '-';
    $ts = strtotime($d);
    if (!$ts) return htmlspecialchars($d);
    return $withTime ? date('d/m/Y', $ts) : date('d/m/Y', $ts);
};

$stageMap = [
    1 => ['label' => 'Engenharia',  'cls' => 'stage--engenharia'],
    2 => ['label' => 'Gestão',      'cls' => 'stage--gestao'],
    3 => ['label' => 'Jurídico',    'cls' => 'stage--juridico'],
    4 => ['label' => 'Pagamento',   'cls' => 'stage--pagamento'],
];
$statusBadge = function (string $st): string {
    return match (mb_strtolower($st)) {
        'approved', 'aprovado', 'aprovada' => 'ops-badge--success',
        'rejected', 'reprovado', 'reprovada' => 'ops-badge--danger',
        default => 'ops-badge--warning',
    };
};
?>

<!-- Hero compacto -->
<section class="ops-hero ops-hero--clean">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div class="ops-hero__stack">
                <h1 class="ops-hero__title">Finalizar Pagamento</h1>
                <p class="ops-hero__subtitle">
                    Operação: <?= htmlspecialchars($file['op_title'] ?? '-') ?>
                </p>
            </div>
            <a href="/operations/<?= (int)$operationId ?>" class="btn btn-outline-brand btn-pill">‹ Voltar</a>
        </div>
    </div>
</section>

<div class="container my-4">

    <!-- Card do arquivo -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-lg-8">
                    <div class="text-muted small">Arquivo</div>
                    <div class="fw-semibold text-dark"><?= htmlspecialchars($file['filename'] ?? '-') ?></div>
                    <?php if (!empty($file['storage_path'])): ?>
                        <div class="mt-2">
                            <a class="btn btn-sm btn-outline-brand btn-pill" target="_blank" href="<?= htmlspecialchars($file['storage_path']) ?>">
                                Abrir / Baixar
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="text-muted small">Enviado em</div>
                    <div class="fw-semibold"><?= $fmt($file['uploaded_at'] ?? null, true) ?></div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="text-muted small">Etapa</div>
                    <span class="stage-badge stage--finalizar">Finalização</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Histórico de análises -->
    <?php if (!empty($reviews)): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header modal-titlebar">
                <div class="modal-titlebar__icon">
                    <svg width="16" height="16" viewBox="0 0 24 24">
                        <path d="M12 8v5l3 2" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" />
                    </svg>
                </div>
                <div class="modal-titlebar__text">Histórico de análises</div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="ops-thead">
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
                            <?php
                            $stageInfo = $stageMap[(int)$r['stage']] ?? ['label' => (int)$r['stage'] . 'ª', 'cls' => 'stage--default'];
                            $cls = $statusBadge((string)($r['status'] ?? ''));
                            $labelPT = match (mb_strtolower((string)$r['status'])) {
                                'approved', 'aprovado', 'aprovada' => 'Aprovado',
                                'rejected', 'reprovado', 'reprovada' => 'Rejeitado',
                                default => ucfirst((string)$r['status']),
                            };
                            ?>
                            <tr>
                                <td><span class="stage-badge <?= $stageInfo['cls'] ?>"><?= htmlspecialchars($stageInfo['label']) ?></span></td>
                                <td><span class="ops-badge <?= $cls ?>"><?= htmlspecialchars($labelPT) ?></span></td>
                                <td><?= htmlspecialchars($r['reviewer_name'] ?? '-') ?></td>
                                <td><?= $fmt($r['reviewed_at'] ?? null, true) ?></td>
                                <td><?= nl2br(htmlspecialchars($r['notes'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php
    $paymentsList = $payments ?? [];
    $total = array_reduce($paymentsList, fn($c, $p) => $c + (float)$p['amount'], 0.0);

    // estatísticas rápidas
    $cnt   = count($paymentsList);
    $dates = array_map(fn($p) => strtotime((string)($p['pay_date'] ?? '')), $paymentsList);
    $dates = array_filter($dates);
    $range =
        $dates
        ? (date('d/m/Y', min($dates)) . ' — ' . date('d/m/Y', max($dates)))
        : '—';

    // badge por método
    $methodBadge = function (?string $m): array {
        $m = mb_strtolower(trim((string)$m));
        return match ($m) {
            'pix'    => ['PIX', 'chip--pix'],
            'ted', 'ted/doc', 'doc' => ['TED', 'chip--ted'],
            'boleto' => ['Boleto', 'chip--boleto'],
            'cartão', 'cartao', 'cartão de crédito', 'cartão de debito', 'crédito', 'débito' => ['Cartão', 'chip--card'],
            default  => [$m !== '' ? ucfirst($m) : '—', 'chip--neutral'],
        };
    };
    ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header modal-titlebar">
            <div class="modal-titlebar__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-credit-card-2-back" viewBox="0 0 16 16">
                    <path d="M11 5.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1-.5-.5z" />
                    <path d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm13 2v5H1V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1m-1 9H2a1 1 0 0 1-1-1v-1h14v1a1 1 0 0 1-1 1" />
                </svg>
            </div>
            <div class="modal-titlebar__text">Pagamentos</div>
            <div class="payments-header__right" title="Somatório dos lançamentos">
                <?= $cnt ?> lançamento<?= $cnt === 1 ? '' : 's' ?>
            </div>
            <div class="payments-header__right" title="Período de pagamento">
                <?= $range ?>
            </div>
        </div>

        <?php if (!$paymentsList): ?>
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-state__title">Nenhum pagamento lançado</div>
                    <div class="empty-state__desc">Registre os pagamentos para concluir a medição.</div>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0 payments-table">
                    <thead class="ops-thead">
                        <tr>
                            <th style="width: 14ch">Data</th>
                            <th style="width: 16ch">Método</th>
                            <th>Descrição</th>
                            <th class="text-end" style="width: 18ch">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentsList as $p): [$mLabel, $mClass] = $methodBadge($p['method'] ?? null); ?>
                            <tr>
                                <td><?= $fmt($p['pay_date'] ?? null, false) ?></td>
                                <td><span class="chip <?= $mClass ?>"><?= htmlspecialchars($mLabel) ?></span></td>
                                <td><?= htmlspecialchars($p['notes'] ?? '-') ?></td>
                                <td class="text-end">R$ <?= number_format((float)$p['amount'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="payments-total">
                            <td colspan="3" class="text-end"><strong>Total</strong></td>
                            <td class="text-end"><strong>R$ <?= number_format($total, 2, ',', '.') ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Confirmação -->
    <form method="post" action="/measurements/<?= (int)$file['id'] ?>/finalize" class="mt-3">
        <div class="alert alert-warning d-flex align-items-center gap-2 shadow-sm">
            <svg width="18" height="18" viewBox="0 0 24 24">
                <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <div>
                Ao confirmar, o status da operação será atualizado para <strong>Concluído</strong> e a medição será bloqueada para edição.
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-brand btn-pill">Confirmar finalização</button>
            <a class="btn btn-outline-brand btn-pill" href="/operations/<?= (int)$operationId ?>">Cancelar</a>
        </div>
    </form>

</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
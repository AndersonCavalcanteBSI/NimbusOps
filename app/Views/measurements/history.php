<?php
$withNav   = true;
$pageTitle = 'Histórico da Medição';
$pageCss   = ['/assets/ops.css'];
include __DIR__ . '/../layout/header.php';

/** Helpers */
$fmt = function (?string $d, bool $withTime = true): string {
    if (!$d) return '-';
    $ts = strtotime($d);
    if (!$ts) return htmlspecialchars($d);
    return $withTime ? date('d/m/Y', $ts) : date('d/m/Y', $ts);
};
$h = fn($s) => htmlspecialchars((string)$s);

/** Dados base (seguros) */
$isFileArray = is_array($file ?? null);
$operationId = isset($operationId) ? (int)$operationId : (int)($file['op_id'] ?? 0);
$opTitle     = $isFileArray ? $h($file['op_title'] ?? '-') : '-';
$fileName    = $isFileArray ? $h($file['filename'] ?? '-')   : '-';
$uploadedAt  = $isFileArray ? $fmt($file['uploaded_at'] ?? null) : '-';
$storePath   = $isFileArray ? (string)($file['storage_path'] ?? '') : '';
$fileStatus  = $isFileArray ? (string)($file['status'] ?? '') : '';
$closedAt    = $isFileArray ? (string)($file['closed_at'] ?? '') : '';

/** Status visual */
$statusLower = mb_strtolower($fileStatus);
$badgeCls = match ($statusLower) {
    'concluído', 'concluido', 'concluido(a)', 'concluída' => 'ops-badge--success',
    'pendente', 'em aberto', 'aberto' => 'ops-badge--warning',
    'rejeitado', 'recusado' => 'ops-badge--danger',
    default => 'ops-badge--neutral',
};
?>

<!-- Hero compacto com CTA Voltar -->
<section class="ops-hero ops-hero--clean">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div class="ops-hero__stack">
                <h1 class="ops-hero__title">Histórico da Medição</h1>
                <p class="ops-hero__subtitle">
                    Operação: <?= $opTitle ?>
                </p>
            </div>
            <a href="/operations/<?= $operationId ?>" class="btn btn-outline-brand btn-pill">‹ Voltar</a>
        </div>
    </div>
</section>

<div class="container my-4">

    <!-- Resumo do arquivo -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6">
                    <div class="text-muted small">Arquivo</div>
                    <div class="fw-semibold text-dark"><?= $fileName ?></div>
                    <?php if ($storePath): ?>
                        <div class="mt-2">
                            <a class="btn btn-sm btn-outline-brand btn-pill" target="_blank" href="<?= $h($storePath) ?>">Abrir / Baixar</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="text-muted small">Enviado em</div>
                    <div class="fw-semibold"><?= $uploadedAt ?></div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="text-muted small">Status da medição</div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="ops-badge <?= $badgeCls ?>"><?= $h($fileStatus ?: '—') ?></span>
                        <?php if ($closedAt !== ''): ?>
                            <span class="chip chip--muted">Finalizada em <?= $fmt($closedAt) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Validações -->
    <?php if (!empty($reviews) && is_array($reviews)): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header modal-titlebar">
                <div class="modal-titlebar__icon">
                    <svg width="16" height="16" viewBox="0 0 24 24">
                        <path d="M12 8v5l3 2" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" />
                    </svg>
                </div>
                <div class="modal-titlebar__text">Validações</div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 ops-table">
                    <thead class="ops-thead">
                        <tr>
                            <th style="width: 8ch">Etapa</th>
                            <th style="width: 14ch">Status</th>
                            <th>Revisor</th>
                            <th style="width: 20ch">Quando</th>
                            <th>Observações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stageNames = [
                            1 => 'Engenharia',
                            2 => 'Gestão',
                            3 => 'Jurídico',
                            4 => 'Pagamento',
                            5 => 'Finalização',
                            6 => 'Completo',
                        ];
                        ?>
                        <?php
                        $stageBadges = [
                            1 => ['Engenharia',  'stage--engenharia'],
                            2 => ['Gestão',      'stage--gestao'],
                            3 => ['Jurídico',    'stage--juridico'],
                            4 => ['Pagamento',   'stage--pagamento'],
                            5 => ['Finalização', 'stage--finalizar'],
                            6 => ['Completo',    'stage--completo'],
                        ];
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
                        <?php foreach ($reviews as $r):
                            $stage = (int)($r['stage'] ?? 0);
                            [$label, $cls] = $stageBadges[$stage] ?? [$stage . 'ª', 'ops-badge--neutral'];
                            $stRaw = (string)($r['status'] ?? '-');
                            $st    = mb_strtolower($stRaw);
                            $stBadge = match ($st) {
                                'approved', 'aprovado', 'aprovada' => ['Aprovado', 'ops-badge--success'],
                                'rejected', 'rejeitado', 'rejeitada' => ['Rejeitado', 'ops-badge--danger'],
                                default => [ucfirst($stRaw ?: '-'), 'ops-badge--warning'],
                            };
                            $who  = $h($r['reviewer_name'] ?? $r['reviewer_id'] ?? '-');
                            $when = $fmt($r['reviewed_at'] ?? null);
                            $notes = $h($r['notes'] ?? '-');
                        ?>
                            <tr>
                                <td><span class="stage-badge <?= $cls ?>"><?= htmlspecialchars($label) ?></span></td>
                                <td><span class="ops-badge <?= $stBadge[1] ?>"><?= $stBadge[0] ?></span></td>
                                <td><?= $who ?></td>
                                <td><?= $when ?></td>
                                <td><?= nl2br($notes) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Pagamentos -->
    <?php if (!empty($payments) && is_array($payments)): ?>
        <?php $total = array_reduce($payments, fn($c, $p) => $c + (float)($p['amount'] ?? 0), 0.0); ?>
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
                    <?= count($payments) ?> lançamento<?= count($payments) === 1 ? '' : 's' ?>
                </div>
                <div class="payments-header__right" title="Somatório dos lançamentos">
                    <span class="payments-total__label">Total</span>
                    <span class="payments-total__value">R$ <?= number_format($total, 2, ',', '.') ?></span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 ops-table payments-table align-middle">
                    <thead class="ops-thead">
                        <tr>
                            <th>Data</th>
                            <th>Valor</th>
                            <th>Método</th>
                            <th>Descrição</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): [$mLabel, $mClass] = $methodBadge($p['method'] ?? null); ?>
                            <tr>
                                <td><?= $fmt($p['pay_date'] ?? null, false) ?></td>
                                <td>R$ <?= number_format((float)($p['amount'] ?? 0), 2, ',', '.') ?></td>
                                <td><span class="chip <?= $mClass ?>"><?= htmlspecialchars($mLabel) ?></span></td>
                                <td><?= $h($p['notes']  ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($reviews) && empty($payments)): ?>
        <div class="alert alert-secondary shadow-sm">Sem registros para esta medição.</div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
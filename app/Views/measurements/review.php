<?php
$withNav   = true;
$pageTitle = 'Análise da Medição - ' . (int)$stage . 'ª Validação';
$pageCss   = ['/assets/ops.css']; // garante estilos
include __DIR__ . '/../layout/header.php';

$fmt = function (?string $d): string {
    if (!$d) return '-';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : $d;
};

$isReadOnly = !empty($readOnly);

// mapa de estágio -> cor da badge
$stageMap = [
    1 => ['label' => 'Engenharia',  'cls' => 'stage--engenharia'],
    2 => ['label' => 'Gestão',      'cls' => 'stage--gestao'],
    3 => ['label' => 'Jurídico',    'cls' => 'stage--juridico'],
    4 => ['label' => 'Pagamento',   'cls' => 'stage--pagamento'],
];
$stageInfo = $stageMap[(int)$stage] ?? ['label' => $stage . 'ª validação', 'cls' => 'stage--default'];
?>

<!-- Hero compacto com botão Voltar à direita -->
<section class="ops-hero ops-hero--clean">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div class="ops-hero__stack">
                <h1 class="ops-hero__title">Análise da Medição</h1>
                <p class="ops-hero__subtitle">
                    Arquivo enviado em <?= $fmt($file['uploaded_at'] ?? null) ?>
                </p>
            </div>

            <a href="/operations/<?= (int)$operationId ?>" class="btn btn-brand btn-pill">
                ‹ Voltar
            </a>
        </div>
    </div>
</section>

<div class="container my-4">

    <?php if ($isReadOnly): ?>
        <div class="alert alert-success mb-3">
            <strong>Medição finalizada.</strong> Modo <em>somente leitura</em>.
            Consulte observações e pagamentos abaixo.
        </div>
    <?php endif; ?>

    <!-- Card do arquivo -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6">
                    <div class="text-muted small">Arquivo</div>
                    <div class="fw-semibold text-dark"><?= htmlspecialchars($file['filename']) ?></div>
                    <?php if (!empty($file['storage_path'])): ?>
                        <div class="mt-2 d-flex gap-2">
                            <a class="btn btn-sm btn-brand" href="<?= htmlspecialchars($file['storage_path']) ?>" target="_blank">Abrir / Baixar</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="text-muted small">Enviado em</div>
                    <div class="fw-semibold"><?= $fmt($file['uploaded_at'] ?? null) ?></div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="text-muted small">Etapa atual</div>
                    <span class="stage-badge <?= $stageInfo['cls'] ?>"><?= htmlspecialchars($stageInfo['label']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Observações anteriores (timeline) -->
    <?php if (!empty($previous) && $stage > 1): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header modal-titlebar">
                <div class="modal-titlebar__icon">
                    <svg width="16" height="16" viewBox="0 0 24 24">
                        <path d="M12 8v5l3 2" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" />
                    </svg>
                </div>
                <div class="modal-titlebar__text">Observações anteriores</div>
            </div>
            <div class="card-body pt-3">
                <ul class="review-timeline list-unstyled mb-0">
                    <?php foreach ($previous as $pr): if ((int)$pr['stage'] >= (int)$stage) continue; ?>
                        <?php
                        // status -> classe + rótulo traduzido
                        $st = (string)($pr['status'] ?? 'pending');
                        $badgeCls  = match ($st) {
                            'approved' => 'ops-badge--success',
                            'rejected' => 'ops-badge--danger',
                            default    => 'ops-badge--warning',
                        };
                        $badgeText = match ($st) {
                            'approved' => 'Aprovado',
                            'rejected' => 'Rejeitado',
                            default    => 'Pendente',
                        };

                        // data no formato dd/mm/yyyy hh:mm:ss
                        $revAt = '-';
                        if (!empty($pr['reviewed_at'])) {
                            $ts = strtotime((string)$pr['reviewed_at']);
                            $revAt = $ts ? date('d/m/Y H:i:s', $ts) : htmlspecialchars((string)$pr['reviewed_at']);
                        }
                        ?>
                        <li class="review-timeline__item">
                            <div class="review-timeline__dot"></div>
                            <div class="review-timeline__content">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div class="fw-semibold">
                                        <?= (int)$pr['stage'] ?>ª validação • <?= htmlspecialchars($pr['reviewer_name'] ?? 'Revisor') ?> • <span class="ops-badge <?= $badgeCls ?>"><?= $badgeText ?></span>
                                    </div>
                                    <div class="text-muted small"><?= $revAt ?></div>
                                </div>
                                <?php if (!empty($pr['notes'])): ?>
                                    <div class="mt-2 text-break"><?= nl2br(htmlspecialchars($pr['notes'])) ?></div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- Etapa 4: Pagamentos -->
    <?php if ((int)$stage === 4): ?>
        <?php if ($isReadOnly): ?>
            <div class="alert alert-info">Pagamentos registrados (somente leitura).</div>
        <?php else: ?>
            <div class="alert alert-info">
                4ª validação (gestão de pagamentos). Registre os pagamentos e depois retorne aqui para aprovar esta etapa.
            </div>
            <p>
                <a class="btn btn-brand btn-pill" href="/measurements/<?= (int)$file['id'] ?>/payments/new" target="_blank">
                    Registrar / Editar Pagamentos
                </a>
            </p>
        <?php endif; ?>

        <?php if (!empty($payments)): ?>
            <?php
            $total = 0.0;
            foreach ($payments as $p) {
                $total += (float)$p['amount'];
            }
            ?>
            <div class="card shadow-sm">
                <div class="card-header payments-header">
                    <div class="payments-header__left">
                        <span class="payments-header__icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24">
                                <path d="M3 7h18M3 12h18M3 17h12" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" />
                            </svg>
                        </span>
                        <span class="payments-header__title">Pagamentos já registrados</span>
                    </div>

                    <div class="payments-header__right" title="Somatório dos lançamentos">
                        <span class="payments-total__label">Total</span>
                        <span class="payments-total__value">R$ <?= number_format($total, 2, ',', '.') ?></span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 payments-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Valor</th>
                                <th>Método</th>
                                <th>Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?= $fmt($p['pay_date'] ?? null) ?></td>
                                    <td>R$ <?= number_format((float)$p['amount'], 2, ',', '.') ?></td>
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

    <br>

    <!-- Form de decisão -->
    <?php if (!$isReadOnly): ?>
        <div class="card shadow-sm">
            <div class="card-header modal-titlebar">
                <div class="modal-titlebar__icon">
                    <svg width="16" height="16" viewBox="0 0 24 24">
                        <path d="M5 12h14M12 5v14" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" />
                    </svg>
                </div>
                <div class="modal-titlebar__text">Registrar decisão</div>
            </div>
            <div class="card-body">
                <?php $hasPayments = !empty($payments) && count($payments) > 0; ?>
                <!--<form id="reviewForm" method="post" action="/measurements/<?= (int)$file['id'] ?>/review/<?= (int)$stage ?>">-->
                <form id="reviewForm" method="post" action="/measurements/<?= (int)$file['id'] ?>/review/<?= (int)$stage ?>" data-stage="<?= (int)$stage ?>" data-has-payments="<?= $hasPayments ? '1' : '0' ?>">
                    <input type="hidden" name="decision" id="decisionField" value="">
                    <div class="mb-3">
                        <label class="form-label ops-label" for="notes">Observações</label>
                        <textarea class="form-control ops-input" id="notes" name="notes" rows="4" required
                            placeholder="Descreva a análise e, se reprovar, detalhe o motivo."></textarea>
                        <div class="form-text"><span id="notesCount">0</span>/2000</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-brand btn-pill" data-decision="approve">
                            Aprovar
                        </button>
                        <button type="submit" class="btn btn-danger btn-pill" data-decision="reject">
                            Reprovar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <!-- Modal: sem pagamentos -->
    <div id="noPaymentsModal" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
            <div class="modal-content" style="border-radius:12px">
                <div class="modal-header" style="border:none">
                    <h5 class="modal-title">Ação não permitida</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>É necessário cadastrar ao menos um pagamento antes de aprovar esta etapa.</p>
                </div>
                <div class="modal-footer" style="border:none">
                    <button type="button" class="btn btn-brand btn-pill" data-bs-dismiss="modal">Entendi</button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    (function() {
        const form = document.getElementById('reviewForm');
        if (!form) return;

        const notes = document.getElementById('notes');
        const count = document.getElementById('notesCount');
        const hidden = document.getElementById('decisionField');

        // contador de caracteres
        function updateCount() {
            if (count && notes) count.textContent = notes.value.length;
        }
        notes && notes.addEventListener('input', updateCount);
        updateCount();

        // define a decisão no hidden quando o usuário clica
        form.querySelectorAll('button[data-decision]').forEach(btn => {
            btn.addEventListener('click', () => {
                hidden.value = btn.getAttribute('data-decision'); // "approve" | "reject"
            });
        });

        // bloqueia aprovação sem pagamentos na 4ª etapa
        form.addEventListener('submit', function(e) {
            const stage = Number(form.dataset.stage || '0');
            const hasPayments = form.dataset.hasPayments === '1';
            const decision = (hidden.value || '').toLowerCase(); // setado no click

            if (stage === 4 && decision === 'approve' && !hasPayments) {
                e.preventDefault();
                const modalEl = document.getElementById('noPaymentsModal');
                if (window.bootstrap && modalEl) {
                    const m = bootstrap.Modal.getOrCreateInstance(modalEl);
                    m.show();
                } else {
                    alert('É necessário cadastrar ao menos um pagamento antes de aprovar esta etapa.');
                }
                return false;
            }

            // trava duplo submit somente quando vai realmente enviar
            form.querySelectorAll('button[type="submit"]').forEach(b => b.disabled = true);
        });
    })();
</script>


<?php include __DIR__ . '/../layout/footer.php'; ?>
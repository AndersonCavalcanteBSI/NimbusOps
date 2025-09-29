<?php
$withNav   = true;
$pageTitle = 'Operação - ' . ($op['title'] ?? 'Detalhes');
$pageCss   = ['/assets/ops.css']; // usa o mesmo CSS da lista
include __DIR__ . '/../layout/header.php';
?>

<section class="ops-hero ops-hero--clean">
    <div class="container">
        <div class="ops-hero__stack d-flex justify-content-between align-items-start">
            <div>
                <h1 class="ops-hero__title">Operação</h1>
                <p class="ops-hero__subtitle">Detalhes, histórico e arquivos de medição</p>
            </div>
            <a href="/operations" class="btn btn-brand d-inline-flex align-items-center gap-2 mb-3">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M10.5 13L5 8l5.5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Voltar
            </a>
        </div>
    </div>
</section>

<div class="container my-4">

    <?php
    $fmt = function (?string $d): string {
        if (!$d) return '-';
        $ts = strtotime($d);
        return $ts ? date('d/m/Y', $ts) : '-';
    };
    ?>

    <!-- Card de resumo -->
    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-3">
                    <div class="text-muted small">Nome</div>
                    <div class="fw-semibold"><?= htmlspecialchars($op['title']) ?></div>
                </div>

                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <?php
                    $opStatus = trim((string)($op['status'] ?? ''));

                    $stageMap = [
                        'Engenharia'  => 'stage--engenharia',
                        'Gestão'      => 'stage--gestao',
                        'Jurídico'    => 'stage--juridico',
                        'Pagamento'   => 'stage--pagamento',
                        'Finalização' => 'stage--finalizar',
                        'Completo'    => 'stage--completo',
                    ];
                    $stageClass = $stageMap[$opStatus] ?? 'stage--default';
                    ?>
                    <span class="stage-badge <?= $stageClass ?>">
                        <?= htmlspecialchars($opStatus !== '' ? $opStatus : '—') ?>
                    </span>
                </div>

                <div class="col-md-3">
                    <div class="text-muted small">Última Medição</div>
                    <div class="fw-semibold"><?= $fmt($lastConcludedAt ?? null) ?></div>
                </div>

                <!--<div class="col-md-2">
                    <div class="text-muted small">Próxima medição</div>
                    <div class="fw-semibold"><?= $fmt($op['next_measurement_at'] ?? null) ?></div>
                </div>-->

                <div class="col-md-3">
                    <div class="text-muted small">Valor da Última Medição</div>
                    <div class="fw-semibold">
                        <?php if ($lastMeasurementTotal !== null): ?>
                            R$ <?= number_format((float)$lastMeasurementTotal, 2, ',', '.') ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#opHistoryModal">
                    Histórico da operação
                </button>
                <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#filesModal">
                    Arquivos de medição
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Histórico da operação (timeline agrupada por dia) -->
    <div class="modal fade" id="opHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content op-hist">
                <div class="modal-header op-hist__header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="op-hist__icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6" />
                                <path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <h5 class="modal-title mb-0">Histórico da operação</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-0">
                    <?php if (!$history): ?>
                        <div class="p-4 text-center text-muted">Sem histórico.</div>
                    <?php else: ?>

                        <?php
                        // --- Agrupa por data (DD-MM-YYYY) e ordena por data/hora desc
                        usort($history, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));

                        $grouped = [];
                        foreach ($history as $h) {
                            $ts = strtotime((string)($h['created_at'] ?? ''));
                            $key = $ts ? date('d/m/Y', $ts) : 'Sem data';
                            $grouped[$key][] = $h;
                        }

                        // Helper de classe do badge por ação
                        $badgeFor = function (string $action): string {
                            return match (strtolower($action)) {
                                'created'               => 'op-hist__badge--neutral',
                                'status_changed'        => 'op-hist__badge--info',
                                'measurement'           => 'op-hist__badge--accent',
                                'payment_recorded'      => 'op-hist__badge--warning',
                                'payment_checked'       => 'op-hist__badge--success',
                                'payment_finalized'     => 'op-hist__badge--success',
                                'mail_sent'             => 'op-hist__badge--neutral',
                                'mail_error'            => 'op-hist__badge--danger',
                                'updated'               => 'op-hist__badge--update',
                                'recipients_updated'    => 'op-hist__badge--success',
                                default                 => 'op-hist__badge--neutral',
                            };
                        };

                        // Novo: mapa de rótulos PT-BR para cada action
                        $labelFor = [
                            'created'               => 'Criado',
                            'status_changed'        => 'Status Alterado',
                            'measurement'           => 'Validação',
                            'payment_recorded'      => 'Pagamento Registrado',
                            'payment_checked'       => 'Pagamento Verificado',
                            'payment_finalized'     => 'Pagamento Concluído', // <- se quiser o typo original, troque aqui
                            'mail_sent'             => 'E-mail Enviado',
                            'mail_error'            => 'Falha no Envio do E-mail',
                            'updated'               => 'Atualização',
                            'recipients_updated'    => 'Destinatários Atualizados',
                        ];
                        ?>

                        <?php foreach ($grouped as $dateLabel => $items): ?>
                            <div class="op-hist__day">
                                <div class="op-hist__dayhead">
                                    <span class="op-hist__daypill">
                                        <?= htmlspecialchars($dateLabel) ?>
                                    </span>
                                    <span class="op-hist__dayline"></span>
                                </div>

                                <ul class="op-hist__timeline list-unstyled m-0">
                                    <?php foreach ($items as $h):
                                        $action     = (string)($h['action'] ?? '');
                                        $actionKey  = strtolower($action);
                                        $badgeClass = $badgeFor($action);
                                        $label      = $labelFor[$actionKey] ?? $action; // << usar rótulo traduzido
                                    ?>
                                        <li class="op-hist__item">
                                            <div class="op-hist__dot"></div>
                                            <div class="op-hist__content">
                                                <div class="d-flex flex-wrap align-items-center gap-2">
                                                    <span class="op-hist__badge <?= $badgeClass ?>">
                                                        <?= htmlspecialchars($label) ?>
                                                    </span>
                                                    <span class="op-hist__meta">
                                                        <?php
                                                        $ts = strtotime((string)($h['created_at'] ?? ''));
                                                        echo $ts ? date('H:i', $ts) : '';
                                                        ?>
                                                        <?php if (!empty($h['user_name'])): ?>
                                                            • por <?= htmlspecialchars($h['user_name']) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($h['notes'])): ?>
                                                    <div class="op-hist__notes">
                                                        <?= nl2br(htmlspecialchars($h['notes'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>

                    <?php endif; ?>
                </div>

                <div class="modal-footer bg-light-subtle">
                    <button type="button" class="btn btn-brand" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal: Arquivos de medição -->
    <div class="modal fade" id="filesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content modal-content--brand">
                <div class="modal-header modal-header--brand">
                    <h5 class="modal-title">
                        <span class="mh-icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6z" stroke="currentColor" stroke-width="1.6" />
                                <path d="M14 2v6h6" stroke="currentColor" stroke-width="1.6" />
                            </svg>
                        </span>
                        Arquivos de medição
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body pt-2">
                    <?php if (!$files): ?>
                        <div class="text-center py-5">
                            <div class="empty-state__title">Nenhum arquivo de medição</div>
                            <div class="empty-state__desc">Quando houver medições, elas aparecerão aqui.</div>
                        </div>
                    <?php else: ?>

                        <div class="list-group list-group-flush">
                            <?php
                            $uid     = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
                            $role    = (string)($_SESSION['user']['role'] ?? '');
                            $isAdmin = ($role === 'admin');

                            // status da operação (p/ decidir Finalizar)
                            $opStatusRaw = (string)($op['status'] ?? '');
                            $opStatusNorm = mb_strtolower(strtr($opStatusRaw, [
                                'Á' => 'A',
                                'À' => 'A',
                                'Â' => 'A',
                                'Ã' => 'A',
                                'á' => 'a',
                                'à' => 'a',
                                'â' => 'a',
                                'ã' => 'a',
                                'É' => 'E',
                                'È' => 'E',
                                'Ê' => 'E',
                                'é' => 'e',
                                'è' => 'e',
                                'ê' => 'e',
                                'Í' => 'I',
                                'Ì' => 'I',
                                'Î' => 'I',
                                'í' => 'i',
                                'ì' => 'i',
                                'î' => 'i',
                                'Ó' => 'O',
                                'Ò' => 'O',
                                'Ô' => 'O',
                                'Õ' => 'O',
                                'ó' => 'o',
                                'ò' => 'o',
                                'ô' => 'o',
                                'õ' => 'o',
                                'Ú' => 'U',
                                'Ù' => 'U',
                                'Û' => 'U',
                                'ú' => 'u',
                                'ù' => 'u',
                                'û' => 'u',
                                'Ç' => 'C',
                                'ç' => 'c'
                            ]), 'UTF-8');

                            $finalizerId = (int)($op['payment_finalizer_user_id'] ?? 0);
                            ?>
                            <?php foreach ($files as $f): ?>
                                <?php
                                $histList   = $filesHistory[$f['id']] ?? [];
                                $fileStatus = (string)($f['file_status'] ?? '');
                                $isDone     = (mb_strtolower($fileStatus, 'UTF-8') === mb_strtolower('Concluído', 'UTF-8'));

                                // Normalizações
                                $fileStatusNorm = mb_strtolower($fileStatus, 'UTF-8');
                                $isFileRejected = in_array($fileStatusNorm, ['rejeitado', 'recusado'], true);
                                $isOpRejected   = in_array($opStatusNorm,   ['rejeitado', 'recusado'], true);

                                // --- NOVO: selo da 1ª etapa = arquivo REJEITADO + closed_at preenchido
                                $closedAtRaw = $f['closed_at'] ?? null;
                                $closedAtTs  = $closedAtRaw ? strtotime((string)$closedAtRaw) : false;
                                $hasSealStage1Rejection = $isFileRejected && !empty($closedAtRaw); // << aqui está o selo

                                // Continua calculando a última etapa rejeitada (para compatibilidade)
                                $lastRejectedStage = null;
                                $lastRejectedAt    = 0;
                                if (!empty($histList)) {
                                    foreach ($histList as $r) {
                                        $st = strtolower((string)($r['status'] ?? ''));
                                        if ($st === 'rejected') {
                                            $ts = 0;
                                            if (!empty($r['reviewed_at'])) {
                                                $ts = strtotime((string)$r['reviewed_at']);
                                            } elseif (!empty($r['created_at'])) {
                                                $ts = strtotime((string)$r['created_at']);
                                            }
                                            if ($ts === false) $ts = 0;
                                            if ($ts >= $lastRejectedAt) {
                                                $lastRejectedAt    = $ts;
                                                $lastRejectedStage = (int)($r['stage'] ?? 0);
                                            }
                                        }
                                    }
                                }

                                // --- BLOQUEIO: se houver selo de recusa (closed_at) então a recusa foi na 1ª etapa e NUNCA reabre
                                $blockedByStage1Rejection = $hasSealStage1Rejection;

                                // Próxima etapa/analisar
                                $nextStage  = (int)($f['next_stage'] ?? 1);
                                $analyzeUrl = !empty($f['review_url'])
                                    ? (string)$f['review_url']
                                    : '/measurements/' . (int)$f['id'] . '/review/' . $nextStage;

                                // Permissão para analisar
                                $expectedReviewerId = match ($nextStage) {
                                    1       => (int)($op['responsible_user_id']    ?? 0),
                                    2       => (int)($op['stage2_reviewer_user_id'] ?? 0),
                                    3       => (int)($op['stage3_reviewer_user_id'] ?? 0),
                                    4       => (int)($op['payment_manager_user_id'] ?? 0),
                                    default => 0,
                                };

                                $canAnalyze = !$isDone
                                    && $nextStage >= 1 && $nextStage <= 4
                                    && !$isOpRejected
                                    && !$blockedByStage1Rejection   // << não permite reabrir se selo ativo
                                    && ($isAdmin || ($uid > 0 && $uid === $expectedReviewerId));

                                $isFinalizationStage = in_array($opStatusNorm, ['finalizar', 'finalizacao'], true);
                                // Recalcula finalização preferencialmente na fase final
                                $canFinalize = !$isDone
                                    && !$isFileRejected
                                    && $isFinalizationStage
                                    && ($isAdmin || ($uid > 0 && $uid === $finalizerId));

                                /*$canFinalize = !$isDone
                                    && !$isFileRejected
                                    && in_array($opStatusNorm, ['finalizar', 'finalizacao'], true)
                                    && ($isAdmin || ($uid > 0 && $uid === $finalizerId));*/

                                // Se pode finalizar, não mostra Analisar
                                if ($canFinalize) {
                                    $canAnalyze = false;
                                }

                                if ($isFinalizationStage) {
                                    $canAnalyze = false;
                                }

                                $finalizeUrl = '/measurements/' . (int)$f['id'] . '/finalize';
                                ?>
                                <div class="list-group-item px-0">
                                    <div class="mf-item d-flex flex-wrap align-items-center gap-3">
                                        <!-- Ícone -->
                                        <div class="mf-icon rounded-3 d-flex align-items-center justify-content-center">
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6z" stroke="currentColor" stroke-width="1.5" />
                                                <path d="M14 2v6h6" stroke="currentColor" stroke-width="1.5" />
                                            </svg>
                                        </div>
                                        <!-- Título + caminho -->
                                        <div class="mf-file flex-grow-1 min-w-0">
                                            <div class="fw-semibold text-dark text-truncate"><?= htmlspecialchars($f['filename']) ?></div>
                                        </div>
                                        <!-- Metas -->
                                        <div class="mf-meta text-muted small">
                                            <div class="text-uppercase opacity-75">Enviado em</div>
                                            <div class="fw-semibold"><?= $fmt($f['uploaded_at'] ?? null) ?></div>
                                        </div>
                                        <!-- Status + ações -->
                                        <div class="mf-actions ms-auto d-flex align-items-center gap-2">
                                            <?php if ($isDone): ?>
                                                <span class="ops-badge ops-badge--success">
                                                    <?= htmlspecialchars($fileStatus !== '' ? $fileStatus : 'Concluído') ?>
                                                </span>
                                            <?php elseif ($hasSealStage1Rejection): ?>
                                                <span class="ops-badge ops-badge--danger">Recusado</span>
                                            <?php elseif ($isFileRejected): ?>
                                                <span class="ops-badge ops-badge--danger">Rejeitado</span>
                                            <?php else: ?>
                                                <span class="ops-badge ops-badge--warning">
                                                    <?= htmlspecialchars($fileStatus !== '' ? $fileStatus : 'Em análise') ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($canAnalyze): ?>
                                                <a class="btn btn-sm btn-brand ms-2" href="<?= htmlspecialchars($analyzeUrl) ?>">
                                                    Analisar<?= isset($f['next_stage']) ? ' (' . (int)$f['next_stage'] . 'ª)' : '' ?>
                                                </a>
                                            <?php elseif ($canFinalize): ?>
                                                <a class="btn btn-sm btn-brand ms-2" href="<?= htmlspecialchars($finalizeUrl) ?>">
                                                    Finalizar
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($f['storage_path'])): ?>
                                                <a class="btn btn-sm btn-brand" href="<?= htmlspecialchars($f['storage_path']) ?>" target="_blank">
                                                    Abrir
                                                </a>
                                            <?php endif; ?>
                                            <a class="btn btn-sm btn-brand" href="<?= htmlspecialchars($f['history_url']) ?>">
                                                Ver histórico
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
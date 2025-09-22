<?php
$withNav   = true;
$pageTitle = 'Criar Nova Operação';
$pageCss   = ['/assets/ops.css']; // garante o CSS da página
include __DIR__ . '/../layout/header.php';

/** helper de opções */
$opts = function (array $users) {
    echo '<option value="">— Selecionar —</option>';
    foreach ($users as $u) {
        $label = htmlspecialchars($u['name'] . ' — ' . $u['email']);
        echo '<option value="' . (int)$u['id'] . '">' . $label . '</option>';
    }
};
?>

<!-- Barra superior enxuta -->
<section class="ops-hero ops-hero--plain ops-hero--compact">
    <div class="container d-flex align-items-center justify-content-between">
        <div>
            <h1 class="ops-hero__title mb-0">Nova Operação</h1>
            <p class="ops-hero__subtitle">Cadastre os dados e defina os responsáveis por cada etapa</p>
        </div>
        <a href="/operations" class="btn btn-outline-brand d-inline-flex align-items-center gap-2 mb-3">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M10.5 13L5 8l5.5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Voltar
        </a>
    </div>
</section>

<div class="container my-4">
    <form class="row g-4" method="post" action="/operations">

        <!-- BLOCO 1: Dados da operação -->
        <div class="col-12">
            <div class="card shadow-sm ops-card">
                <div class="card-header ops-card__header">
                    <div class="ops-card__title">
                        <span class="ops-card__icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M4 7h16M4 12h16M4 17h10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                            </svg>
                        </span>
                        Dados da operação
                    </div>
                    <div class="small text-muted">Campos obrigatórios marcados com *</div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label ops-label">Código *</label>
                            <input type="text"
                                name="code"
                                class="form-control"
                                value="<?= htmlspecialchars($nextCode ?? '') ?>"
                                readonly>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label ops-label">Título *</label>
                            <input type="text" name="title" class="form-control ops-input" required placeholder="Nome da operação">
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- BLOCO 2: Responsáveis e notificações -->
        <div class="card mt-3 shadow-sm ops-card">
            <div class="card-header ops-card__header d-flex align-items-center gap-2">
                <!--<div class="card-header d-flex align-items-center gap-2" style="background:#0f2238;color:#fff">-->
                <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width:26px;height:26px;background:rgba(255,255,255,.12)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                        <path d="M12 6v6l4 2" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <strong>Responsáveis por etapa & Notificações</strong>
                <span class="ms-auto small opacity-75">Defina quem valida e quem é notificado em caso de reprovação</span>
            </div>

            <div class="card-body">
                <?php
                $sel = fn($v, $id) => ((int)($v ?? 0) === (int)$id ? 'selected' : '');
                $op  = $op ?? [];
                ?>

                <div class="row g-4">
                    <!-- Coluna esquerda: seleções -->
                    <div class="col-lg-8">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">1ª validação (Engenharia)</label>
                                <select class="form-select js-pick" name="responsible_user_id" data-placeholder="— Selecionar —">
                                    <option value="">— Selecionar —</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= (int)$u['id'] ?>" <?= $sel($op['responsible_user_id'] ?? null, $u['id']) ?>>
                                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Quem inicia a análise técnica da medição.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">2ª validação (Gestão)</label>
                                <select class="form-select js-pick" name="stage2_reviewer_user_id" data-placeholder="— Selecionar —">
                                    <option value="">— Selecionar —</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= (int)$u['id'] ?>" <?= $sel($op['stage2_reviewer_user_id'] ?? null, $u['id']) ?>>
                                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">3ª validação (Jurídico)</label>
                                <select class="form-select js-pick" name="stage3_reviewer_user_id" data-placeholder="— Selecionar —">
                                    <option value="">— Selecionar —</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= (int)$u['id'] ?>" <?= $sel($op['stage3_reviewer_user_id'] ?? null, $u['id']) ?>>
                                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">4ª Pagamentos (Gestão)</label>
                                <select class="form-select js-pick" name="payment_manager_user_id" data-placeholder="— Selecionar —">
                                    <option value="">— Selecionar —</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= (int)$u['id'] ?>" <?= $sel($op['payment_manager_user_id'] ?? null, $u['id']) ?>>
                                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Finalização (após pagamentos)</label>
                                <select class="form-select js-pick" name="payment_finalizer_user_id" data-placeholder="— Selecionar —">
                                    <option value="">— Selecionar —</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= (int)$u['id'] ?>" <?= $sel($op['payment_finalizer_user_id'] ?? null, $u['id']) ?>>
                                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Quem confirma e encerra a medição.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Notificar em caso de reprovação <span class="text-muted">(até 2)</span></label>
                                <select name="rejection_notify_user_ids[]" class="form-select js-multi" multiple size="6" data-max="2">
                                    <?php foreach (($users ?? []) as $u): ?>
                                        <option value="<?= (int)$u['id'] ?>">
                                            <?= htmlspecialchars($u['name']) ?> — <?= htmlspecialchars($u['email']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="d-flex justify-content-between">
                                    <div class="form-text">Segure Ctrl (Win) ou Cmd (macOS) para selecionar.</div>
                                    <div class="form-text"><span id="notifCount">0</span>/2 selecionados</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Coluna direita: resumo -->
                    <div class="col-lg-4">
                        <div class="p-3 rounded-3 border" style="background:#f7fafc;border-color:#e6edf3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                    <path d="M20 7l-8 10-5-5" stroke="#0b1d33" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <strong class="text-dark">Resumo das atribuições</strong>
                            </div>
                            <ul class="list-unstyled small mb-0" id="assigneesSummary">
                                <li><span class="text-muted">Engenharia:</span> <em>-</em></li>
                                <li><span class="text-muted">Gestão:</span> <em>-</em></li>
                                <li><span class="text-muted">Jurídico:</span> <em>-</em></li>
                                <li><span class="text-muted">Pagamentos:</span> <em>-</em></li>
                                <li><span class="text-muted">Finalização:</span> <em>-</em></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Ações -->
        <div class="col-12 d-flex gap-2 justify-content-end">
            <a class="btn btn-outline-brand" href="/operations">Cancelar</a>
            <button class="btn btn-brand">Criar operação</button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Limite e contador do multi-select
        const multi = document.querySelector('select.js-multi');
        const notifCount = document.getElementById('notifCount');

        function updateCount() {
            const total = Array.from(multi.options).filter(o => o.selected).length;
            notifCount.textContent = total;
        }
        if (multi) {
            multi.addEventListener('change', () => {
                const selected = Array.from(multi.options).filter(o => o.selected);
                if (selected.length > (parseInt(multi.dataset.max || '2', 10))) {
                    selected[0].selected = false; // mantém só os últimos 2
                }
                updateCount();
            });
            updateCount();
        }

        // Resumo das atribuições
        const map = {
            responsible_user_id: 'Engenharia:',
            stage2_reviewer_user_id: 'Gestão:',
            stage3_reviewer_user_id: 'Jurídico:',
            payment_manager_user_id: 'Pagamentos:',
            payment_finalizer_user_id: 'Finalização:'
        };
        const summary = document.getElementById('assigneesSummary');

        function labelOf(opt) {
            return opt ? opt.text.replace(/\s*\(.+\)$/, '') : '-';
        }

        function refreshSummary() {
            if (!summary) return;
            Object.keys(map).forEach((name, idx) => {
                const sel = document.querySelector('select[name="' + name + '"]');
                const li = summary.children[idx];
                const opt = sel && sel.selectedIndex > 0 ? sel.options[sel.selectedIndex] : null;
                li.innerHTML = '<span class="text-muted">' + map[name] + '</span> <strong>' + (opt ? labelOf(opt) : '-') + '</strong>';
            });
        }
        document.querySelectorAll('.js-pick').forEach(el => {
            el.addEventListener('change', refreshSummary);
        });
        refreshSummary();

        // OPCIONAL: tornar selects pesquisáveis com Tom Select (se quiser)
        // Basta descomentar as 3 linhas abaixo e incluir os assets do Tom Select (bloco seguinte).

        document.querySelectorAll('.js-pick').forEach(el => new TomSelect(el, {
            create: false,
            allowEmptyOption: true,
            maxOptions: 1000,
            sortField: {
                field: "text",
                direction: "asc"
            }
        }));
        new TomSelect(multi, {
            create: false,
            maxItems: 2,
            plugins: ['remove_button']
        });
    });
</script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sel = document.querySelector('select[name="rejection_notify_user_ids[]"]');
        if (!sel) return;
        sel.addEventListener('change', function() {
            const selected = Array.from(this.options).filter(o => o.selected);
            if (selected.length > 2) selected[0].selected = false;
        });
    });
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
<?php
$withNav   = true;
$pageTitle = 'Registrar Pagamentos - ' . ($file['op_title'] ?? 'Operação');
$pageCss   = ['/assets/ops.css']; // garante estilos do tema
include __DIR__ . '/../layout/header.php';

$fmt = function (?string $d): string {
    if (!$d) return '-';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : $d;
};
?>

<!-- Hero compacto com botão Voltar -->
<section class="ops-hero ops-hero--clean">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div class="ops-hero__stack">
                <h1 class="ops-hero__title">Registrar Pagamentos</h1>
                <p class="ops-hero__subtitle">
                    Operação: <?= htmlspecialchars($file['op_title']) ?>
                </p>
            </div>
            <a href="/operations/<?= (int)$file['op_id'] ?>" class="btn btn-brand btn-pill">‹ Voltar</a>
        </div>
    </div>
</section>

<div class="container my-4">

    <?php if (isset($_GET['ok'])): ?>
        <div class="alert alert-success shadow-sm">
            Pagamentos registrados com sucesso. <strong>Lembre-se de conciliar no banco.</strong>
        </div>
    <?php else: ?>
        <div class="alert alert-info shadow-sm">
            Esta medição foi aprovada nas 3 validações. Informe os pagamentos desta medição abaixo.
        </div>
    <?php endif; ?>

    <!-- Card do arquivo -->
    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <div class="text-muted small">Arquivo</div>
                <div class="fw-semibold"><?= htmlspecialchars($file['filename']) ?></div>
                <div class="small text-muted mt-1">Enviado em <?= $fmt($file['uploaded_at'] ?? null) ?></div>
            </div>
            <?php if (!empty($file['storage_path'])): ?>
                <a class="btn btn-brand btn-pill" target="_blank" href="<?= htmlspecialchars($file['storage_path']) ?>">
                    Abrir / Baixar
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulário de pagamentos -->
    <div class="card shadow-sm mb-4">
        <div class="card-header modal-titlebar">
            <div class="modal-titlebar__icon">
                <svg width="16" height="16" viewBox="0 0 24 24">
                    <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" />
                </svg>
            </div>
            <div class="modal-titlebar__text">Lançamentos</div>
            <div class="ms-auto small text-muted">Adicione um ou mais pagamentos</div>
        </div>

        <form id="payForm" method="post" action="/measurements/<?= (int)$file['id'] ?>/payments">
            <div class="card-body">
                <div id="rows" class="vstack gap-2">
                    <!-- Linha modelo (clonada via JS) -->
                    <div class="row g-2 align-items-end pay-row d-none" data-prototype>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Data *</label>
                            <input type="date" class="form-control ops-input" name="pay_date[]" disabled>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Valor *</label>
                            <input type="text" inputmode="decimal" class="form-control ops-input pay-amount" placeholder="0,00" name="amount[]" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Forma</label>
                            <input type="text" class="form-control ops-input" name="method[]" placeholder="PIX, TED, Boleto…" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Descrição</label>
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control ops-input" name="notes[]" placeholder="Opcional" disabled>
                                <button type="button" class="btn btn-outline-danger btn-pill pay-remove" title="Remover linha">×</button>
                            </div>
                        </div>
                    </div>

                    <!-- Primeira linha visível -->
                    <div class="row g-2 align-items-end pay-row">
                        <div class="col-6 col-md-3">
                            <label class="form-label">Data *</label>
                            <input type="date" class="form-control ops-input" name="pay_date[]" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Valor *</label>
                            <input type="text" inputmode="decimal" class="form-control ops-input pay-amount" placeholder="0,00" name="amount[]" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Forma</label>
                            <input type="text" class="form-control ops-input" name="method[]" placeholder="PIX, TED, Boleto…">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Descrição</label>
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control ops-input" name="notes[]" placeholder="Opcional">
                                <button type="button" class="btn btn-outline-danger btn-pill pay-remove" title="Remover linha">×</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rodapé do card: adicionar linha + total -->
            <div class="card-footer d-flex flex-wrap align-items-center justify-content-between gap-2">
                <button type="button" id="addRow" class="btn btn-brand btn-pill">
                    + Adicionar pagamento
                </button>
                <div class="ms-auto">
                    <span class="text-muted me-2">Total</span>
                    <span id="totalLabel" class="fw-semibold">R$ 0,00</span>
                </div>
            </div>

            <div class="p-3 d-flex gap-2">
                <!--<button class="btn btn-brand btn-pill">Salvar pagamentos</button>-->
                <button id="saveBtn" class="btn btn-brand btn-pill" disabled>Salvar pagamentos</button>
                <a href="/operations/<?= (int)$file['op_id'] ?>" class="btn btn-brand btn-pill">Cancelar</a>
            </div>
        </form>
    </div>

    <!-- Tabela de pagamentos já registrados -->
    <?php if (!empty($payments)): ?>
        <?php $total = array_reduce($payments, fn($c, $p) => $c + (float)$p['amount'], 0.0); ?>
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

</div>

<script>
    (function() {
        const rowsWrap = document.getElementById('rows');
        const addRowBtn = document.getElementById('addRow');
        const totalEl = document.getElementById('totalLabel');
        const saveBtn = document.getElementById('saveBtn'); // <-- NOVO

        // máscara/parse de moeda PT-BR
        function parseBRL(str) {
            if (!str) return 0;
            const s = String(str).replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '');
            const n = parseFloat(s);
            return isNaN(n) ? 0 : n;
        }

        function fmtBRL(n) {
            return 'R$ ' + Number(n).toFixed(2)
                .replace('.', ',')
                .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // ---- NOVO: verifica se há pelo menos um lançamento válido
        function hasAtLeastOneValidRow() {
            const rows = Array.from(rowsWrap.querySelectorAll('.pay-row'))
                .filter(r => !r.hasAttribute('data-prototype'));

            for (const row of rows) {
                const dateEl = row.querySelector('input[name="pay_date[]"]');
                const amtEl = row.querySelector('input[name="amount[]"]');
                const valNum = parseBRL(amtEl?.value || '');
                if ((dateEl?.value || '') !== '' && valNum > 0) return true;
            }
            return false;
        }

        // ---- NOVO: atualiza estado do botão Salvar
        function updateSaveEnabled() {
            const ok = hasAtLeastOneValidRow();
            saveBtn.disabled = !ok;
            saveBtn.title = ok ? '' : 'Adicione pelo menos um pagamento válido (data e valor).';
        }

        function updateTotal() {
            let sum = 0;
            document.querySelectorAll('.pay-amount').forEach(inp => {
                sum += parseBRL(inp.value);
            });
            totalEl.textContent = fmtBRL(sum);
            updateSaveEnabled(); // <-- NOVO: sincroniza botão
        }

        // máscara ao digitar
        function maskOnInput(e) {
            let v = e.target.value.replace(/[^\d]/g, '');
            if (v === '') {
                e.target.value = '';
                updateTotal();
                return;
            }
            while (v.length < 3) v = '0' + v;
            let intPart = v.slice(0, -2);
            const decPart = v.slice(-2);
            intPart = intPart.replace(/^0+(?=\d)/, '');
            if (intPart === '') intPart = '0';
            const intFmt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            e.target.value = 'R$ ' + intFmt + ',' + decPart;
            updateTotal();
        }

        function maskOnBlur(e) {
            const n = parseBRL(e.target.value);
            e.target.value = n ? fmtBRL(n) : '';
            updateTotal(); // garante revalidação após blur
        }

        // delega remover linha
        rowsWrap.addEventListener('click', function(ev) {
            const btn = ev.target.closest('.pay-remove');
            if (!btn) return;
            const row = btn.closest('.pay-row');
            if (!row) return;
            if (rowsWrap.querySelectorAll('.pay-row:not([data-prototype])').length <= 1) {
                row.querySelectorAll('input').forEach(i => i.value = '');
                updateTotal();
                return;
            }
            row.remove();
            updateTotal();
        });

        // NOVO: revalida quando datas mudam (captura mudanças em qualquer linha)
        rowsWrap.addEventListener('change', function(ev) {
            if (ev.target && ev.target.name === 'pay_date[]') {
                updateSaveEnabled();
            }
        });

        // adiciona nova linha
        addRowBtn.addEventListener('click', function() {
            const proto = rowsWrap.querySelector('[data-prototype]');
            const clone = proto.cloneNode(true);
            clone.classList.remove('d-none');
            clone.removeAttribute('data-prototype');

            clone.querySelectorAll('input').forEach(i => {
                i.disabled = false;
                i.value = '';
            });
            const dateInput = clone.querySelector('input[name="pay_date[]"]');
            const amtInput = clone.querySelector('input[name="amount[]"]');
            if (dateInput) dateInput.required = true;
            if (amtInput) amtInput.required = true;

            // liga máscara e blur no novo campo
            clone.querySelectorAll('.pay-amount').forEach(i => {
                i.addEventListener('input', maskOnInput);
                i.addEventListener('blur', maskOnBlur); // <-- NOVO
            });

            rowsWrap.appendChild(clone);
            updateSaveEnabled(); // <-- NOVO
        });

        // ligar máscara nos existentes
        document.querySelectorAll('.pay-amount').forEach(i => {
            i.addEventListener('input', maskOnInput);
            i.addEventListener('blur', maskOnBlur); // <-- NOVO
        });

        // calcula total e estado do botão ao carregar
        updateTotal();
        updateSaveEnabled();

        // validação final no submit (mantém sua regra atual)
        document.getElementById('payForm').addEventListener('submit', function(e) {
            if (!hasAtLeastOneValidRow()) {
                e.preventDefault();
                alert('Informe ao menos um pagamento válido (data e valor).');
                return;
            }
            // normaliza valores PT-BR -> float com ponto
            document.querySelectorAll('.pay-amount').forEach(inp => {
                const val = parseBRL(inp.value);
                inp.value = val.toString();
            });
        });
    })();
</script>


<?php include __DIR__ . '/../layout/footer.php'; ?>
<?php
$withNav   = true;
$pageTitle = 'Upload de Medição';
$pageCss   = ['/assets/ops.css']; // garante a paleta/estilos reutilizados
$ops = $operations ?? [];
usort($ops, fn($a, $b) => strcasecmp($a['title'] ?? '', $b['title'] ?? ''));
include __DIR__ . '/../layout/header.php';
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>

<section class="ops-hero ops-hero--clean">
    <div class="container">
        <div class="ops-hero__stack d-flex justify-content-between align-items-start">
            <div>
                <h1 class="ops-hero__title">Upload de Medição</h1>
                <p class="ops-hero__subtitle">Envie o arquivo da medição para iniciar o fluxo de validações</p>
            </div>
            <a href="/operations" class="btn btn-brand btn-pill d-inline-flex align-items-center gap-2">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Voltar
            </a>
        </div>
    </div>
</section>

<div class="container my-4">
    <?php if (isset($_GET['ok'])): ?>
        <div class="alert alert-success d-flex align-items-center gap-2">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10Z" stroke="currentColor" stroke-width="1.5" />
                <path d="M7.75 12.25 10.5 15l5.75-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <div>
                Arquivo enviado com sucesso. A operação foi marcada como <strong>Engenharia</strong> e o responsável notificado.
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form id="uploadForm" class="row g-4" method="post" action="/measurements/upload" enctype="multipart/form-data" novalidate>
                <!-- Operação -->
                <div class="col-lg-6">
                    <label class="form-label ops-label">Operação <span class="text-danger">*</span></label>

                    <!-- Campo de busca que filtra as opções do select -->
                    <div class="position-relative mb-2">
                        <input id="opSearch" type="text" class="form-control ops-input"
                            placeholder="Buscar por #id, título, código, emissor…">
                        <span class="position-absolute top-50 end-0 translate-middle-y me-3 text-muted" style="pointer-events:none;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm10 2-5-5" stroke="currentColor" stroke-width="1.6"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>

                    <select id="operationSelect" name="operation_id"
                        class="form-select ops-input op-select-tall" required>
                        <option value="">— Selecionar —</option>
                        <?php foreach ($ops as $o): ?>
                            <?php
                            $id    = (int)$o['id'];
                            $title = (string)$o['title'];
                            $code  = trim((string)($o['code'] ?? ''));
                            $issuer = trim((string)($o['issuer'] ?? ''));
                            // texto completo para busca (id + título + código + emissor)
                            $search = "#{$id} {$title} {$code} {$issuer}";
                            ?>
                            <option value="<?= $id ?>"
                                data-search="<?= htmlspecialchars(mb_strtolower($search, 'UTF-8')) ?>">
                                <?= htmlspecialchars($title) ?>
                                <?= $code !== '' ? ' — ' . htmlspecialchars($code) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="form-text">Dica: digite para filtrar; setas ↑/↓ e Enter para escolher.</div>
                </div>

                <!-- Dropzone / arquivo -->
                <div class="col-lg-6">
                    <label class="form-label ops-label">Arquivo de medição <span class="text-danger">*</span></label>

                    <!-- área de drop / clique -->
                    <div id="dropzone" class="border rounded-4 p-4 text-center"
                        style="border-style:dashed; border-color:#d6e0eb; background:#f8fbff;">
                        <input id="fileInput" type="file" name="file" accept=".pdf,.xlsx,.xls,.csv" class="visually-hidden" required>
                        <div class="d-flex flex-column align-items-center gap-2">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 16V4m0 0 4 4m-4-4-4 4M6 20h12" stroke="#0e2d52" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="fw-semibold">Arraste e solte aqui, ou <a href="#" id="clickToChoose">clique para selecionar</a></div>
                            <div class="small text-muted">Tipos aceitos: <strong>PDF, XLSX, XLS, CSV</strong> • Máx: <strong>20MB</strong></div>
                            <div id="fileChip" class="d-none mt-2"></div>
                        </div>
                    </div>
                </div>

                <!-- Barra de ações -->
                <div class="col-12 d-flex gap-2 justify-content-end">
                    <button id="submitBtn" class="btn btn-brand btn-pill d-inline-flex align-items-center gap-2">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <span>Enviar</span>
                    </button>
                    <a class="btn btn-brand btn-pill" href="/operations">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        const input = document.getElementById('opSearch');
        const select = document.getElementById('operationSelect');
        if (!input || !select) return;

        // deixa a lista “alta” ao focar e volta ao normal ao sair (só visual)
        select.addEventListener('focus', () => select.classList.add('op-select-open'));
        select.addEventListener('blur', () => select.classList.remove('op-select-open'));

        // filtra options pelo conteúdo em data-search
        function filterOptions(q) {
            const query = (q || '').toLowerCase().trim();
            let firstVisible = null;

            Array.from(select.options).forEach((opt, idx) => {
                if (idx === 0) { // mantém placeholder
                    opt.hidden = false;
                    return;
                }
                const hay = opt.getAttribute('data-search') || '';
                const match = hay.indexOf(query) !== -1;
                opt.hidden = !match;
                if (match && !firstVisible) firstVisible = opt;
            });

            // se havia algo selecionado e filtrou fora, limpa
            if (select.selectedIndex > 0 && select.options[select.selectedIndex].hidden) {
                select.selectedIndex = 0;
            }
            // se nada selecionado e existe primeiro visível, destaca (não seleciona)
            if (!select.value && firstVisible) {
                select.selectedIndex = Array.from(select.options).indexOf(firstVisible);
            }
        }

        input.addEventListener('input', e => filterOptions(e.target.value));

        // Enter no campo de busca confirma a opção destacada
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (select.selectedIndex > 0) {
                    // mantém foco lógico no select para quem usa setas
                    select.focus();
                }
            }
            // setas sobem/descem a lista mesmo estando no input
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                select.focus();
            }
        });

        // ao mudar no select, reflete no input (mostra o rótulo escolhido)
        select.addEventListener('change', () => {
            const opt = select.options[select.selectedIndex];
            input.value = opt && opt.value ? opt.text.replace(/\s+/g, ' ').trim() : '';
        });
    })();
</script>

<script>
    (function() {
        const MAX = 20 * 1024 * 1024; // 20MB
        const ACCEPT = ['pdf', 'xlsx', 'xls', 'csv'];

        const form = document.getElementById('uploadForm');
        const input = document.getElementById('fileInput');
        const dropzone = document.getElementById('dropzone');
        const clicker = document.getElementById('clickToChoose');
        const chip = document.getElementById('fileChip');
        const submitBtn = document.getElementById('submitBtn');
        const spinner = submitBtn.querySelector('.spinner-border');

        const fmtSize = b => {
            if (b < 1024) return b + ' B';
            if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
            return (b / (1024 * 1024)).toFixed(1) + ' MB';
        };

        function showChip(file) {
            chip.className = 'd-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill bg-white border';
            chip.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 8V6a2 2 0 0 1 2-2h5l5 5v9a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2v-1" stroke="#0e2d52" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="fw-semibold">${file.name}</span>
                <span class="text-muted small">(${fmtSize(file.size)})</span>
                <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="removeFile">Remover</button>
            `;
            chip.classList.remove('d-none');
            document.getElementById('removeFile').onclick = () => {
                input.value = '';
                chip.classList.add('d-none');
            };
        }

        function validate(file) {
            if (!file) return false;
            const ext = (file.name.split('.').pop() || '').toLowerCase();
            if (!ACCEPT.includes(ext)) {
                alert('Tipo de arquivo não permitido. Aceitos: PDF, XLSX, XLS, CSV.');
                input.value = '';
                return false;
            }
            if (file.size > MAX) {
                alert('Arquivo muito grande. Tamanho máximo: 20MB.');
                input.value = '';
                return false;
            }
            return true;
        }

        // Click para abrir o seletor
        clicker.addEventListener('click', (e) => {
            e.preventDefault();
            input.click();
        });

        // Estado de foco da dropzone
        ;
        ['dragenter', 'dragover'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.style.borderColor = '#0e2d52';
                dropzone.style.background = '#f2f7ff';
            });
        });;
        ['dragleave', 'drop'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.style.borderColor = '#d6e0eb';
                dropzone.style.background = '#f8fbff';
            });
        });

        // Soltar arquivo
        dropzone.addEventListener('drop', e => {
            const file = e.dataTransfer.files[0];
            if (validate(file)) {
                input.files = e.dataTransfer.files;
                showChip(file);
            }
        });

        // Seleção via diálogo
        input.addEventListener('change', () => {
            const file = input.files[0];
            if (validate(file)) {
                showChip(file);
            }
        });

        // Loading no submit
        form.addEventListener('submit', () => {
            submitBtn.setAttribute('disabled', 'disabled');
            spinner.classList.remove('d-none');
        });
    })();
</script>

<script>
    (function() {
        const form = document.getElementById('uploadForm');
        const op = document.getElementById('operation_id');

        if (form && op) {
            form.addEventListener('submit', function(e) {
                const val = parseInt(op.value || '0', 10);
                if (!val || val <= 0) {
                    e.preventDefault();
                    alert('Selecione uma operação antes de enviar o arquivo.');
                    op.focus();
                }
            });
        }

        // Se vier do servidor um flash de erro, mostra o pop-up também
        <?php if (!empty($flashError)): ?>
            alert(<?= json_encode($flashError, JSON_UNESCAPED_UNICODE) ?>);
        <?php endif; ?>
    })();
</script>


<?php include __DIR__ . '/../layout/footer.php'; ?>
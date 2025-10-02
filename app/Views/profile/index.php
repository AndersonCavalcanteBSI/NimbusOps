<?php
$withNav   = true;
$pageTitle = 'Meu Perfil - ' . ($user['name'] ?? '');
$pageCss   = ['/assets/ops.css'];
include __DIR__ . '/../layout/header.php';

$user         = $user ?? [];
$flashSuccess = $flashSuccess ?? null;
$flashError   = $flashError ?? null;

$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$avatar = (string)($user['avatar'] ?? '');
?>

<section class="ops-hero ops-hero--clean">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div class="ops-hero__stack">
                <h1 class="ops-hero__title">Meu Perfil</h1>
                <p class="ops-hero__subtitle">Gerencie suas informações e segurança</p>
            </div>
        </div>
    </div>
</section>

<div class="container my-4">
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success shadow-sm"><?= $esc($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger shadow-sm"><?= $esc($flashError) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header modal-titlebar">
                    <div class="modal-titlebar__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle-fill" viewBox="0 0 16 16">
                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2" />
                        </svg>
                    </div>
                    <div class="modal-titlebar__text">Informações pessoais</div>
                </div>

                <!-- FORM PERFIL (nome/avatar) -->
                <form method="post" action="/profile" enctype="multipart/form-data" class="card-body vstack gap-3">
                    <!-- CSRF do perfil -->
                    <input type="hidden" name="csrf_profile" value="<?= $esc($csrf_profile ?? '') ?>">

                    <div class="d-flex align-items-center gap-3">
                        <?php
                        $avatarSrc = !empty($user['avatar'])
                            ? htmlspecialchars((string)$user['avatar'], ENT_QUOTES)
                            : avatarPlaceholder((string)$user['name'], 120);
                        ?>
                        <img class="avatar-xl" src="<?= $avatarSrc ?>" alt="Avatar" onerror="this.onerror=null;this.src='<?= avatarPlaceholder((string)$user['name'], 120) ?>';">
                        <div>
                            <label class="form-label">Alterar avatar</label>
                            <input type="file" name="avatar" class="form-control" accept=".png,.jpg,.jpeg,.webp">
                            <div class="form-text">PNG/JPG/WEBP, até 2MB.</div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Nome</label>
                        <input type="text" class="form-control ops-input" name="name" value="<?= $esc($user['name'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control ops-input" value="<?= $esc($user['email'] ?? '') ?>" disabled>
                        <div class="form-text">O e-mail não pode ser alterado por aqui.</div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-brand btn-pill">Salvar alterações</button>
                        <a class="btn btn-brand btn-pill" href="/operations">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header modal-titlebar">
                    <div class="modal-titlebar__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-microsoft" viewBox="0 0 16 16">
                            <path d="M7.462 0H0v7.19h7.462zM16 0H8.538v7.19H16zM7.462 8.211H0V16h7.462zm8.538 0H8.538V16H16z" />
                        </svg>
                    </div>
                    <div class="modal-titlebar__text">Integrações</div>
                </div>
                <div class="card-body vstack gap-2">
                    <?php $linked = (int)($user['ms_linked'] ?? 0) === 1; ?>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-semibold d-flex align-items-center gap-2">
                                Conta Microsoft
                                <?php if ($linked): ?>
                                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle">• Conectada</span>
                                <?php else: ?>
                                    <span class="badge rounded-pill bg-secondary-subtle text-muted border border-secondary-subtle">• Não conectada</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <?php if ($linked): ?>
                                <a class="btn btn-danger btn-pill" href="/auth/microsoft/unlink">Desvincular</a>
                            <?php else: ?>
                                <a class="btn btn-microsoft btn-pill" href="/auth/microsoft">
                                    <svg width="16" height="16" viewBox="0 0 23 23" aria-hidden="true" class="me-1">
                                        <rect x="1" y="1" width="9.5" height="9.5" fill="#F35325" />
                                        <rect x="12.5" y="1" width="9.5" height="9.5" fill="#81BC06" />
                                        <rect x="1" y="12.5" width="9.5" height="9.5" fill="#05A6F0" />
                                        <rect x="12.5" y="12.5" width="9.5" height="9.5" fill="#FFBA08" />
                                    </svg>
                                    Conectar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr>
                    <div class="small text-muted">
                        Autentique com sua conta corporativa Microsoft. Para alterar permissões ou e-mail, contate o administrador.
                    </div>
                </div>
            </div>

            <br>

            <div class="card shadow-sm">
                <div class="card-header modal-titlebar">
                    <div class="modal-titlebar__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock-fill" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M8 0a4 4 0 0 1 4 4v2.05a2.5 2.5 0 0 1 2 2.45v5a2.5 2.5 0 0 1-2.5 2.5h-7A2.5 2.5 0 0 1 2 13.5v-5a2.5 2.5 0 0 1 2-2.45V4a4 4 0 0 1 4-4m0 1a3 3 0 0 0-3 3v2h6V4a3 3 0 0 0-3-3" />
                        </svg>
                    </div>
                    <div class="modal-titlebar__text">Senha</div>
                </div>

                <!-- FORM SENHA (separado) -->
                <form method="post" action="/profile/password" class="card-body vstack gap-3">
                    <!-- CSRF da senha -->
                    <input type="hidden" name="csrf_pwd" value="<?= $esc($csrf_pwd ?? '') ?>">

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Senha atual</label>
                            <div class="position-relative">
                                <input type="password" class="form-control ops-input" name="current_password" id="pwd-current" autocomplete="current-password">
                                <button type="button" class="btn btn-sm btn-light position-absolute end-0 top-0 mt-1 me-1"
                                    data-toggle-pwd="#pwd-current" aria-label="Mostrar/ocultar senha">👁️</button>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label">Nova senha</label>
                            <div class="position-relative">
                                <input type="password" class="form-control ops-input" name="new_password" id="pwd-new" autocomplete="new-password">
                                <button type="button" class="btn btn-sm btn-light position-absolute end-0 top-0 mt-1 me-1"
                                    data-toggle-pwd="#pwd-new" aria-label="Mostrar/ocultar senha">👁️</button>
                            </div>

                            <div class="mt-2">
                                <div class="progress" style="height:6px;">
                                    <div id="pwd-meter-bar" class="progress-bar" role="progressbar" style="width:0%"></div>
                                </div>
                                <div id="pwd-meter-text" class="small mt-1 text-muted">Força da senha</div>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label">Confirmar nova senha</label>
                            <div class="position-relative">
                                <input type="password" class="form-control ops-input" name="new_password_confirm" id="pwd-confirm" autocomplete="new-password">
                                <button type="button" class="btn btn-sm btn-light position-absolute end-0 top-0 mt-1 me-1"
                                    data-toggle-pwd="#pwd-confirm" aria-label="Mostrar/ocultar senha">👁️</button>
                            </div>
                            <div id="pwd-match" class="small mt-1"></div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-2">
                        <button class="btn btn-brand btn-pill">Atualizar senha</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        document.querySelectorAll('[data-toggle-pwd]').forEach(btn => {
            btn.addEventListener('click', () => {
                const sel = btn.getAttribute('data-toggle-pwd');
                const inp = document.querySelector(sel);
                if (!inp) return;
                inp.type = (inp.type === 'password') ? 'text' : 'password';
                btn.textContent = (inp.type === 'password') ? '👁️' : '🙈';
            });
        });

        const newPwd = document.getElementById('pwd-new');
        const confirmPwd = document.getElementById('pwd-confirm');
        const bar = document.getElementById('pwd-meter-bar');
        const text = document.getElementById('pwd-meter-text');
        const match = document.getElementById('pwd-match');

        function scorePassword(pw) {
            let score = 0;
            if (!pw) return 0;
            const sets = [/[a-z]/, /[A-Z]/, /[0-9]/, /[^a-zA-Z0-9]/];
            if (pw.length >= 8) score += 1;
            if (pw.length >= 12) score += 1;
            score += sets.reduce((acc, rgx) => acc + (rgx.test(pw) ? 1 : 0), 0);
            if (pw.length >= 16) score += 1;
            return Math.min(score, 6);
        }

        function updateMeter() {
            const pw = newPwd.value;
            const s = scorePassword(pw);
            const pct = Math.round((s / 6) * 100);
            bar.style.width = pct + '%';

            let label = 'Muito fraca',
                cls = 'bg-danger';
            if (s >= 2) {
                label = 'Fraca';
                cls = 'bg-danger';
            }
            if (s >= 3) {
                label = 'Média';
                cls = 'bg-warning';
            }
            if (s >= 4) {
                label = 'Forte';
                cls = 'bg-success';
            }
            if (s >= 5) {
                label = 'Muito forte';
                cls = 'bg-success';
            }
            bar.className = 'progress-bar ' + cls;
            text.textContent = 'Força da senha: ' + label;

            if (confirmPwd.value.length > 0) {
                if (confirmPwd.value === pw) {
                    match.textContent = 'As senhas coincidem.';
                    match.className = 'small mt-1 text-success';
                } else {
                    match.textContent = 'As senhas não coincidem.';
                    match.className = 'small mt-1 text-danger';
                }
            } else {
                match.textContent = '';
                match.className = 'small mt-1';
            }
        }

        if (newPwd) newPwd.addEventListener('input', updateMeter);
        if (confirmPwd) confirmPwd.addEventListener('input', updateMeter);
    })();
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
<?php
$withNav   = true;
$pageTitle = $title ?? 'Ação não permitida';
$pageCss   = ['/assets/ops.css'];
include __DIR__ . '/../layout/header.php';
?>

<section class="ops-hero ops-hero--clean">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div class="ops-hero__stack">
                <h1 class="ops-hero__title"><?= htmlspecialchars($title ?? 'Ação não permitida') ?></h1>
                <?php if (!empty($subtitle)): ?>
                    <p class="ops-hero__subtitle"><?= htmlspecialchars($subtitle) ?></p>
                <?php endif; ?>
            </div>
            <?php if (!empty($backHref)): ?>
                <a href="<?= htmlspecialchars($backHref) ?>" class="btn btn-brand btn-pill">‹ Voltar</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="container my-4">
    <div class="card shadow-sm">
        <div class="card-body d-flex gap-3 align-items-start">
            <div class="empty-state__icon" aria-hidden="true" style="flex:0 0 auto">
                <!-- ícone “info/alert” -->
                <svg width="28" height="28" viewBox="0 0 24 24">
                    <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"
                        stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>

            <div class="flex-grow-1">
                <div class="empty-state__title" style="font-weight:700; color:#0B2A4A; font-size:18px;">
                    <?= htmlspecialchars($heading ?? 'Não foi possível continuar') ?>
                </div>
                <?php if (!empty($message)): ?>
                    <p class="mb-2" style="margin-top:6px"><?= $message ?></p>
                <?php endif; ?>

                <?php if (!empty($details)): ?>
                    <div class="small text-muted"><?= $details ?></div>
                <?php endif; ?>

                <div class="mt-3 d-flex gap-2">
                    <?php if (!empty($primaryHref) && !empty($primaryLabel)): ?>
                        <a href="<?= htmlspecialchars($primaryHref) ?>" class="btn btn-brand btn-pill">
                            <?= htmlspecialchars($primaryLabel) ?>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($secondaryHref) && !empty($secondaryLabel)): ?>
                        <a href="<?= htmlspecialchars($secondaryHref) ?>" class="btn btn-outline-secondary btn-pill">
                            <?= htmlspecialchars($secondaryLabel) ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
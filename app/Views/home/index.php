<?php
$withNav = true;
$pageTitle = 'Home';
include __DIR__ . '/../layout/header.php';
?>
<div class="p-5 mb-4 bg-light rounded-3">
    <div class="container-fluid py-5">
        <h1 class="display-5 fw-bold">Bem-vindo ao NimbusOps</h1>
        <p class="col-md-8 fs-5">Starter modular em PHP 8.2 com Bootstrap, PDO e middlewares de segurança.
        </p>
        <a href="/operations" class="btn btn-primary btn-lg">Ver Operações</a>
    </div>
</div>
<?php include __DIR__ . '/../layout/footer.php'; ?>
<?php include __DIR__ . '/../layout/header.php'; ?>
<h2 class="mb-3">Login</h2>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="/auth/local" class="row g-3">
    <div class="col-md-6">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" class="form-control" required autofocus>
    </div>
    <div class="col-md-6">
        <label class="form-label">Senha</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Entrar</button>
        <!-- Botão Microsoft: link direto para a rota /auth/microsoft -->
        <a class="btn btn-outline-dark" href="/auth/microsoft">Entrar com Microsoft</a>
    </div>
</form>

<hr class="my-4">

<div class="text-muted small">
    Seu e-mail precisa estar habilitado no sistema para autenticar pela Microsoft.
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
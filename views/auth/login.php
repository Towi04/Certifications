<section class="auth-card">
    <p class="brand-mark" aria-hidden="true">⬡</p>
    <h1>Iniciar sesión</h1>
    <p class="muted">Administradores y Teacher Referral.</p>
    <p class="slogan">be different, be better</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($info)): ?>
        <div class="alert alert-ok"><?= e($info) ?></div>
    <?php endif; ?>

    <form method="post" action="/login" class="stack">
        <label>
            Usuario
            <input type="text" name="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>" placeholder="user">
        </label>
        <label>
            Contraseña
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn">Entrar</button>
    </form>
</section>

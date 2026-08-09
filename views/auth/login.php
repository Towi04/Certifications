<section class="auth-card">
    <p class="brand-mark"><img src="/assets/brand/escudo.svg" width="48" height="58" alt="Instituto DOCEO"></p>
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
    <div class="auth-links">
        <a class="linkish" href="/forgot-password">Olvidé mi contraseña</a>
        <a class="linkish" href="/register">Registrarme</a>
    </div>
</section>

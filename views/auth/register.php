<section class="auth-card">
    <p class="brand-mark"><img src="/assets/brand/escudo.png" width="48" height="58" alt="Instituto DOCEO"></p>
    <h1>Registro</h1>
    <p class="muted">Crea tu cuenta para acceder al sistema.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($info)): ?>
        <div class="alert alert-ok"><?= e($info) ?></div>
    <?php endif; ?>

    <form method="post" action="/register" class="stack">
        <label>
            Nombre completo
            <input type="text" name="name" required autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>">
        </label>
        <label>
            Correo electrónico
            <input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>">
        </label>
        <label>
            Contraseña
            <input type="password" name="password" required autocomplete="new-password">
        </label>
        <label>
            Repite la contraseña
            <input type="password" name="password_confirm" required autocomplete="new-password">
        </label>
        <button type="submit" class="btn">Crear cuenta</button>
    </form>

    <div class="auth-links">
        <a class="linkish" href="/login">Ya tengo cuenta</a>
        <a class="linkish" href="/forgot-password">Olvidé mi contraseña</a>
    </div>
</section>

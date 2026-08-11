<section class="auth-card">
    <p class="brand-mark"><img src="/assets/brand/escudo.svg" width="48" height="58" alt="Instituto DOCEO"></p>
    <h1>Iniciar sesión</h1>
    <p class="muted">Alumnos, Teacher Referral y equipo Doceo.</p>
    <p class="slogan">be different, be better</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($info)): ?>
        <div class="alert alert-ok"><?= e($info) ?></div>
    <?php endif; ?>

    <form method="post" action="/login" class="stack">
        <label>
            Correo o usuario
            <input type="text" name="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>" placeholder="tunombre@correo.com">
        </label>
        <label>
            Contraseña
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <p class="muted" style="margin:0;font-size:0.9rem">
            Si te registraste al adquirir una certificación, entra con el <strong>correo</strong> del mensaje de acceso (no hace falta inventar un usuario).
        </p>
        <button type="submit" class="btn">Entrar</button>
    </form>
    <div class="auth-links">
        <a class="linkish" href="/forgot-password">Olvidé mi contraseña</a>
        <a class="linkish" href="/register">Registrarme</a>
    </div>
</section>

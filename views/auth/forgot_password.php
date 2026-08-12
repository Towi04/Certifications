<section class="auth-card">
    <p class="brand-mark"><img src="/assets/brand/escudo.png" width="48" height="58" alt="Instituto DOCEO"></p>
    <h1>Olvidé mi contraseña</h1>
    <p class="muted">Te enviaremos un enlace para restablecer tu contraseña.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($info)): ?>
        <div class="alert alert-ok"><?= e($info) ?></div>
    <?php endif; ?>

    <form method="post" action="/forgot-password" class="stack">
        <label>
            Correo electrónico
            <input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>">
        </label>
        <button type="submit" class="btn">Enviar enlace</button>
    </form>

    <div class="auth-links">
        <a class="linkish" href="/login">Volver al login</a>
        <a class="linkish" href="/register">Registrarme</a>
    </div>
</section>

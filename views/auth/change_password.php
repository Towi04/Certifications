<section class="auth-card">
    <p class="brand-mark"><img src="/assets/brand/escudo.svg" width="48" height="58" alt="Instituto DOCEO"></p>
    <h1>Cambia tu contraseña</h1>
    <p class="muted">
        Por seguridad debes definir una contraseña nueva antes de continuar.
        No uses la contraseña temporal <code>Doceo1234</code>.
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($info)): ?>
        <div class="alert alert-ok"><?= e($info) ?></div>
    <?php endif; ?>

    <form method="post" action="/change-password" class="stack">
        <label>
            Nueva contraseña
            <input type="password" name="password" required minlength="8" autocomplete="new-password">
        </label>
        <label>
            Repite la nueva contraseña
            <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
        </label>
        <button type="submit" class="btn">Guardar y continuar</button>
    </form>
</section>

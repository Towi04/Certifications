<section class="auth-card">
    <p class="brand-mark" aria-hidden="true">⬡</p>
    <h1>Restablecer contraseña</h1>
    <p class="muted">Elige una nueva contraseña para tu cuenta.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($info)): ?>
        <div class="alert alert-ok"><?= e($info) ?></div>
    <?php endif; ?>

    <form method="post" action="/reset-password" class="stack">
        <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
        <label>
            Nueva contraseña
            <input type="password" name="password" required autocomplete="new-password">
        </label>
        <label>
            Repite la nueva contraseña
            <input type="password" name="password_confirm" required autocomplete="new-password">
        </label>
        <button type="submit" class="btn">Guardar contraseña</button>
    </form>

    <div class="auth-links">
        <a class="linkish" href="/login">Volver al login</a>
    </div>
</section>

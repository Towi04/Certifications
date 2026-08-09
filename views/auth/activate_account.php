<section class="auth-card">
    <p class="brand-mark"><img src="/assets/brand/escudo.svg" width="48" height="58" alt="Instituto DOCEO"></p>
    <h1>Activa tu cuenta</h1>
    <p class="muted">
        Confirma tu acceso eligiendo una contraseña nueva.
        No uses la contraseña temporal <code>Doceo1234</code>.
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($info)): ?>
        <div class="alert alert-ok"><?= e($info) ?></div>
    <?php endif; ?>

    <?php if (!empty($activationUser)): ?>
        <div class="profile-summary stack">
            <div><strong>Nombre:</strong> <?= e($activationUser['name'] ?? '') ?></div>
            <div><strong>Correo:</strong> <?= e($activationUser['email'] ?? '') ?></div>
            <div><strong>Usuario:</strong> <code><?= e($activationUser['username'] ?? '') ?></code></div>
        </div>

        <form method="post" action="/activate-account" class="stack">
            <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
            <label>
                Nueva contraseña
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
            </label>
            <label>
                Repite la nueva contraseña
                <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
            </label>
            <button type="submit" class="btn">Activar y guardar contraseña</button>
        </form>
    <?php else: ?>
        <div class="auth-links">
            <a class="linkish" href="/login">Ir al login</a>
        </div>
    <?php endif; ?>
</section>

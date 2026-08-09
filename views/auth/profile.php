<section class="auth-card">
    <p class="brand-mark"><img src="/assets/brand/escudo.svg" width="48" height="58" alt="Instituto DOCEO"></p>
    <h1>Mi perfil</h1>
    <p class="muted">Cambia tu contraseña segura y revisa tu información.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($info)): ?>
        <div class="alert alert-ok"><?= e($info) ?></div>
    <?php endif; ?>

    <div class="profile-summary stack">
        <div><strong>Nombre:</strong> <?= e($user['name'] ?? $user['email'] ?? '') ?></div>
        <div><strong>Correo:</strong> <?= e($user['email'] ?? '') ?></div>
        <div><strong>Rol:</strong> <?= e($user['role'] ?? '') ?></div>
    </div>

    <form method="post" action="/profile" class="stack">
        <label>
            Contraseña actual
            <input type="password" name="current_password" required autocomplete="current-password">
        </label>
        <label>
            Nueva contraseña
            <input type="password" name="password" required autocomplete="new-password">
        </label>
        <label>
            Repite la nueva contraseña
            <input type="password" name="password_confirm" required autocomplete="new-password">
        </label>
        <button type="submit" class="btn">Actualizar contraseña</button>
    </form>
</section>

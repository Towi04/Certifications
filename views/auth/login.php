<section class="auth-card">
    <h1>Iniciar sesión</h1>
    <p class="muted">Administradores y Teacher Referral.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login" class="stack">
        <label>
            Correo
            <input type="email" name="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>">
        </label>
        <label>
            Contraseña
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn">Entrar</button>
    </form>
</section>

<?php
$item = $item ?? [];
$old = is_array($old ?? null) ? $old : [];
$showLogin = !empty($old['show_login']);
?>
<section class="page-head">
    <div>
        <p class="eyebrow"><?= e($item['provider_name'] ?? '') ?></p>
        <h1>Adquirir · <?= e($item['name']) ?></h1>
        <p class="muted">
            Precio:
            <?php if ($item['public_price'] !== null): ?>
                <strong><?= e(\App\Support\Str::money((float)$item['public_price'], $item['currency'] ?? 'MXN')) ?></strong>
            <?php else: ?>
                a consultar
            <?php endif; ?>
        </p>
    </div>
    <a class="btn btn-ghost" href="/certificacion?slug=<?= e(rawurlencode($item['slug'])) ?>">Volver a la ficha</a>
</section>

<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (!empty($info)): ?><div class="alert alert-ok"><?= e($info) ?></div><?php endif; ?>

<div class="acquire-grid">
    <section class="note <?= $showLogin ? 'is-dim' : '' ?>" id="register-panel">
        <h2>Soy nuevo · crear acceso de alumno</h2>
        <p class="muted">Con estos datos creamos tu cuenta solo para seguimiento de la certificación.</p>
        <form method="post" action="/adquirir" class="stack form-grid">
            <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
            <input type="hidden" name="mode" value="register">
            <label>Nombre<input name="first_name" required value="<?= e((string)($old['first_name'] ?? '')) ?>"></label>
            <label>Apellido<input name="last_name" required value="<?= e((string)($old['last_name'] ?? '')) ?>"></label>
            <label>Correo<input type="email" name="email" required value="<?= e((string)($old['email'] ?? '')) ?>"></label>
            <label>Teléfono<input name="phone" value="<?= e((string)($old['phone'] ?? '')) ?>"></label>
            <label>Contraseña<input type="password" name="password" required minlength="8"></label>
            <label>Confirmar contraseña<input type="password" name="password_confirm" required minlength="8"></label>
            <div class="actions" style="grid-column:1/-1">
                <button class="btn" type="submit">Crear cuenta y continuar</button>
            </div>
        </form>
    </section>

    <section class="note" id="login-panel">
        <h2>Ya tengo cuenta</h2>
        <p class="muted">Inicia sesión para vincular esta certificación a tu seguimiento.</p>
        <form method="post" action="/adquirir" class="stack form-grid">
            <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
            <input type="hidden" name="mode" value="login">
            <label>Correo<input type="email" name="email" required value="<?= e((string)($old['email'] ?? '')) ?>"></label>
            <label>Contraseña<input type="password" name="password" required></label>
            <div class="actions" style="grid-column:1/-1">
                <button class="btn" type="submit">Entrar y adquirir</button>
            </div>
        </form>
    </section>
</div>

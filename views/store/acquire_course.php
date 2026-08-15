<?php
$item = $item ?? [];
$old = is_array($old ?? null) ? $old : [];
$user = $user ?? null;
$loggedIn = !empty($logged_in);
$showLogin = !empty($old['show_login']) && !$loggedIn;

$val = static function (string $key, string $fallback = '') use ($old): string {
    return (string) ($old[$key] ?? $fallback);
};

$uFirst = (string) ($user['first_name'] ?? '');
$uLast = (string) ($user['last_name'] ?? '');
$uEmail = (string) ($user['email'] ?? '');
$uPhone = (string) ($user['phone'] ?? '');

$platform = strtolower((string) ($item['platform_type'] ?? ''));
$platformHint = match ($platform) {
    'moodle' => 'Tras confirmar el pago recibirás acceso automático a campus Doceo (Moodle).',
    'ethinking' => 'Tras el pago, Doceo compra/solicita el cupo en eThinking y te envía los accesos cuando estén listos.',
    'xperienceed' => 'Tras el pago, Doceo solicita el curso a XperienceEd y te envía los accesos cuando el proveedor los habilite.',
    default => 'Tras el pago, Doceo te enviará las instrucciones de acceso.',
};
$price = $item['public_price'] !== null ? (float) $item['public_price'] : 0.0;
$slug = (string) ($item['slug'] ?? '');
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Curso</p>
        <h1>Adquirir · <?= e($item['name'] ?? '') ?></h1>
        <p class="muted">
            Precio:
            <?php if ($item['public_price'] !== null): ?>
                <strong><?= e(\App\Support\Str::money($price, $item['currency'] ?? 'MXN')) ?></strong>
            <?php else: ?>
                a consultar
            <?php endif; ?>
        </p>
        <p class="muted"><?= e($platformHint) ?></p>
    </div>
    <a class="btn btn-ghost" href="/curso?slug=<?= e(rawurlencode($slug !== '' ? $slug : (string) ($item['id'] ?? ''))) ?>">Volver</a>
</section>

<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (!empty($info)): ?><div class="alert alert-ok"><?= e($info) ?></div><?php endif; ?>

<section class="note">
    <?php if (!$loggedIn && $showLogin): ?>
        <form method="post" action="/adquirir-curso" class="stack form-grid" style="margin-bottom:1.25rem">
            <input type="hidden" name="slug" value="<?= e($slug) ?>">
            <input type="hidden" name="mode" value="login">
            <h3 style="grid-column:1/-1;margin:0">Inicia sesión</h3>
            <label>Correo<input type="email" name="email" required value="<?= e($val('email')) ?>"></label>
            <label>Contraseña<input type="password" name="password" required></label>
            <div class="actions" style="grid-column:1/-1">
                <button class="btn" type="submit">Iniciar sesión y continuar</button>
            </div>
        </form>
    <?php endif; ?>

    <form method="post" action="/adquirir-curso" class="stack form-grid">
        <input type="hidden" name="slug" value="<?= e($slug) ?>">
        <input type="hidden" name="mode" value="register">
        <?php if (!$loggedIn): ?>
            <p class="muted" style="grid-column:1/-1;margin:0">
                Creamos tu cuenta de alumno automáticamente y te enviamos la contraseña por correo.
            </p>
        <?php endif; ?>

        <label>Nombre(s)
            <input name="first_name" required value="<?= e($val('first_name', $uFirst)) ?>">
        </label>
        <label>Apellido paterno
            <input name="last_name_p" required value="<?= e($val('last_name_p', $uLast)) ?>">
        </label>
        <label>Apellido materno
            <input name="last_name_m" value="<?= e($val('last_name_m')) ?>">
        </label>
        <label>Correo
            <input type="email" name="email" required value="<?= e($val('email', $uEmail)) ?>" <?= $loggedIn ? 'readonly' : '' ?>>
        </label>
        <label>Teléfono
            <input name="phone" value="<?= e($val('phone', $uPhone)) ?>">
        </label>

        <div class="actions" style="grid-column:1/-1">
            <button class="btn" type="submit"><?= $loggedIn ? 'Continuar con mi solicitud' : 'Crear cuenta y continuar' ?></button>
            <?php if (!$loggedIn && !$showLogin): ?>
                <a class="btn btn-ghost" href="/adquirir-curso?slug=<?= e(rawurlencode($slug)) ?>&login=1">Ya tengo cuenta</a>
            <?php endif; ?>
        </div>
    </form>
</section>

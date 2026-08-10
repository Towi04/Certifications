<?php
$item = $item ?? [];
$old = is_array($old ?? null) ? $old : [];
$user = $user ?? null;
$loggedIn = !empty($logged_in);
$showLogin = !empty($old['show_login']) && !$loggedIn;

$uFirst = (string) ($user['first_name'] ?? '');
$uLast = (string) ($user['last_name'] ?? '');
$uEmail = (string) ($user['email'] ?? '');
$uPhone = (string) ($user['phone'] ?? '');
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

<section class="note acquire-warning">
    <h2>Datos para tu certificado</h2>
    <p>
        Los datos que captures aquí serán los que aparecen en tu <strong>certificación oficial</strong>.
        Escribe tu nombre y apellidos <strong>exactamente</strong> como en tu identificación oficial.
    </p>
</section>

<?php if (!$loggedIn): ?>
<section class="note <?= $showLogin ? '' : 'is-dim' ?>" id="login-panel">
    <h2>¿Ya tienes cuenta?</h2>
    <p class="muted">Inicia sesión y completa el formulario de candidato.</p>
    <form method="post" action="/adquirir" class="stack form-grid">
        <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
        <input type="hidden" name="mode" value="login">
        <label>Correo<input type="email" name="email" required value="<?= e((string)($old['email'] ?? '')) ?>"></label>
        <label>Contraseña<input type="password" name="password" required></label>
        <div class="actions" style="grid-column:1/-1">
            <button class="btn btn-ghost" type="submit">Entrar</button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="note" id="register-panel">
    <h2><?= $loggedIn ? 'Completa tus datos de candidato' : 'Registro del candidato' ?></h2>
    <?php if (!$loggedIn): ?>
        <p class="muted">
            Al enviar creamos tu acceso automáticamente y te enviamos un correo con la contraseña temporal.
            No necesitas inventar una contraseña ahora.
        </p>
    <?php else: ?>
        <p class="muted">Sesión: <?= e($uEmail) ?></p>
    <?php endif; ?>

    <form method="post" action="/adquirir" class="stack form-grid">
        <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
        <input type="hidden" name="mode" value="<?= $loggedIn ? 'confirm' : 'register' ?>">

        <label>Nombre(s)
            <input name="first_name" required autocomplete="given-name"
                   value="<?= e((string)($old['first_name'] ?? $uFirst)) ?>"
                   placeholder="Como en tu INE">
        </label>
        <label>Apellido paterno
            <input name="last_name_p" required autocomplete="family-name"
                   value="<?= e((string)($old['last_name_p'] ?? $uLast)) ?>">
        </label>
        <label>Apellido materno
            <input name="last_name_m" autocomplete="additional-name"
                   value="<?= e((string)($old['last_name_m'] ?? '')) ?>">
        </label>
        <label>Correo
            <input type="email" name="email" required autocomplete="email"
                   value="<?= e((string)($old['email'] ?? $uEmail)) ?>"
                   <?= $loggedIn ? 'readonly' : '' ?>>
        </label>
        <label>Teléfono
            <input name="phone" autocomplete="tel"
                   value="<?= e((string)($old['phone'] ?? $uPhone)) ?>">
        </label>
        <label>CURP
            <input name="curp" maxlength="18"
                   value="<?= e((string)($old['curp'] ?? '')) ?>"
                   placeholder="18 caracteres" style="text-transform:uppercase">
        </label>
        <label>Fecha de nacimiento
            <input type="date" name="birth_date" value="<?= e((string)($old['birth_date'] ?? '')) ?>">
        </label>
        <label>Sexo
            <select name="sex">
                <option value="">—</option>
                <option value="F" <?= ($old['sex'] ?? '') === 'F' ? 'selected' : '' ?>>Femenino</option>
                <option value="M" <?= ($old['sex'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option>
            </select>
        </label>
        <label>Nacionalidad
            <input name="nationality" value="<?= e((string)($old['nationality'] ?? 'MEX')) ?>" maxlength="40">
        </label>
        <label>Fecha preferida de examen
            <input type="date" name="exam_date" required value="<?= e((string)($old['exam_date'] ?? '')) ?>">
        </label>

        <div class="actions" style="grid-column:1/-1">
            <button class="btn" type="submit"><?= $loggedIn ? 'Continuar con mi solicitud' : 'Enviar solicitud' ?></button>
        </div>
    </form>
</section>

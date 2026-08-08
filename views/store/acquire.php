<?php
$item = $item ?? [];
$old = is_array($old ?? null) ? $old : [];
$user = $user ?? null;
$showLogin = !empty($old['show_login']) && !$user;
$loggedIn = is_array($user) && !empty($user['id']);
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

<div class="acquire-layout">
    <section class="note <?= $showLogin ? 'is-dim' : '' ?>" id="register-panel">
        <h2>Datos para agendar tu examen</h2>
        <div class="alert alert-warn">
            <strong>Importante:</strong> los datos que captures aquí serán los que aparezcan en tu certificación.
            Escribe tu nombre y apellidos <em>exactamente</em> como figuran en tu identificación oficial.
        </div>

        <form method="post" action="/adquirir" class="stack form-grid">
            <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
            <input type="hidden" name="mode" value="<?= $loggedIn ? 'confirm' : 'register' ?>">

            <label>Nombre(s)
                <input name="first_name" required autocomplete="given-name"
                       value="<?= e((string)($old['first_name'] ?? '')) ?>"
                       placeholder="Tal cual en tu identificación">
            </label>
            <label>Apellido paterno
                <input name="last_name_p" required autocomplete="family-name"
                       value="<?= e((string)($old['last_name_p'] ?? '')) ?>">
            </label>
            <label>Apellido materno
                <input name="last_name_m" autocomplete="additional-name"
                       value="<?= e((string)($old['last_name_m'] ?? '')) ?>">
            </label>
            <label>Correo
                <input type="email" name="email" required autocomplete="email"
                       value="<?= e((string)($old['email'] ?? '')) ?>">
            </label>
            <label>Teléfono / WhatsApp
                <input name="phone" required autocomplete="tel"
                       value="<?= e((string)($old['phone'] ?? '')) ?>"
                       placeholder="10 dígitos">
            </label>
            <label>Fecha de nacimiento
                <input type="date" name="birth_date" required
                       value="<?= e((string)($old['birth_date'] ?? '')) ?>">
            </label>
            <label>Sexo
                <select name="sex" required>
                    <?php $sx = (string)($old['sex'] ?? ''); ?>
                    <option value="">—</option>
                    <option value="F" <?= $sx === 'F' ? 'selected' : '' ?>>Femenino</option>
                    <option value="M" <?= $sx === 'M' ? 'selected' : '' ?>>Masculino</option>
                </select>
            </label>
            <label>Nacionalidad (código)
                <input name="nationality" required maxlength="3"
                       value="<?= e((string)($old['nationality'] ?? 'MEX')) ?>"
                       placeholder="MEX">
            </label>
            <label>Fecha deseada de examen
                <input type="date" name="exam_date" required min="<?= e(date('Y-m-d')) ?>"
                       value="<?= e((string)($old['exam_date'] ?? '')) ?>">
            </label>
            <label>Hora preferida
                <input name="exam_time" placeholder="11:00"
                       value="<?= e((string)($old['exam_time'] ?? '')) ?>">
            </label>

            <div class="actions" style="grid-column:1/-1">
                <button class="btn" type="submit">Continuar</button>
            </div>
            <p class="muted field-wide" style="margin:0">
                Al continuar verás el reglamento para firmar y el link de pago.
                Te enviaremos un correo para dar seguimiento a tu examen.
            </p>
        </form>
    </section>

    <?php if (!$loggedIn): ?>
        <section class="note" id="login-panel">
            <h2>Ya tengo acceso</h2>
            <p class="muted">Si ya adquiriste antes, inicia sesión y completa el formulario de esta misma página.</p>
            <form method="post" action="/adquirir" class="stack form-grid">
                <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                <input type="hidden" name="mode" value="login">
                <label>Correo<input type="email" name="email" required value="<?= e((string)($old['email'] ?? '')) ?>"></label>
                <label>Contraseña<input type="password" name="password" required></label>
                <div class="actions" style="grid-column:1/-1">
                    <button class="btn btn-ghost" type="submit">Entrar</button>
                </div>
            </form>
            <p class="muted"><a href="/forgot-password">Olvidé mi contraseña</a></p>
        </section>
    <?php endif; ?>
</div>

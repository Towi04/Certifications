<?php
$item = $item ?? [];
$old = is_array($old ?? null) ? $old : [];
$user = $user ?? null;
$loggedIn = !empty($logged_in);
$showLogin = !empty($old['show_login']) && !$loggedIn;

$regConfig = \App\Catalog\CatalogRepository::decodeRegistrationConfig($item['registration_fields_json'] ?? null);
$regFields = $regConfig['modes'];
$regCatalog = \App\Catalog\CatalogRepository::registrationFieldCatalog();
$regCustom = $regConfig['custom'];
$schedule = $regConfig['schedule'];
$timeSlots = \App\Catalog\CatalogRepository::examTimeSlots($schedule);
$isOn = static fn (string $key): bool => \App\Catalog\CatalogRepository::registrationFieldEnabled($regFields, $key);
$isReq = static fn (string $key): bool => \App\Catalog\CatalogRepository::registrationFieldRequired($regFields, $key);

$uFirst = (string) ($user['first_name'] ?? '');
$uLast = (string) ($user['last_name'] ?? '');
$uEmail = (string) ($user['email'] ?? '');
$uPhone = (string) ($user['phone'] ?? '');

$val = static function (string $key, string $fallback = '') use ($old): string {
    return (string) ($old[$key] ?? $fallback);
};
$extraOld = is_array($old['extra'] ?? null) ? $old['extra'] : [];
$publicPrice = $item['public_price'] !== null ? (float) $item['public_price'] : 0.0;
$extraFee = (float) ($schedule['extraordinary_fee'] ?? 0);
?>
<section class="page-head">
    <div>
        <p class="eyebrow"><?= e($item['provider_name'] ?? '') ?></p>
        <h1>Adquirir · <?= e($item['name']) ?></h1>
        <p class="muted">
            Precio base:
            <?php if ($item['public_price'] !== null): ?>
                <strong><?= e(\App\Support\Str::money($publicPrice, $item['currency'] ?? 'MXN')) ?></strong>
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
        <label>Correo<input type="email" name="email" required value="<?= e($val('email')) ?>"></label>
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

    <form method="post" action="/adquirir" class="stack form-grid" id="acquireForm">
        <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
        <input type="hidden" name="mode" value="<?= $loggedIn ? 'confirm' : 'register' ?>">

        <?php if ($isOn('first_name')): ?>
            <label><?= e($regCatalog['first_name']['label']) ?>
                <input name="first_name" <?= $isReq('first_name') ? 'required' : '' ?> autocomplete="given-name"
                       value="<?= e($val('first_name', $uFirst)) ?>" placeholder="Como en tu identificación">
            </label>
        <?php endif; ?>

        <?php if ($isOn('last_name_p')): ?>
            <label><?= e($regCatalog['last_name_p']['label']) ?>
                <input name="last_name_p" <?= $isReq('last_name_p') ? 'required' : '' ?> autocomplete="family-name"
                       value="<?= e($val('last_name_p', $uLast)) ?>">
            </label>
        <?php endif; ?>

        <?php if ($isOn('last_name_m')): ?>
            <label><?= e($regCatalog['last_name_m']['label']) ?>
                <input name="last_name_m" <?= $isReq('last_name_m') ? 'required' : '' ?> autocomplete="additional-name"
                       value="<?= e($val('last_name_m')) ?>">
            </label>
        <?php endif; ?>

        <?php if ($isOn('email')): ?>
            <label><?= e($regCatalog['email']['label']) ?>
                <input type="email" name="email" <?= $isReq('email') ? 'required' : '' ?> autocomplete="email"
                       value="<?= e($val('email', $uEmail)) ?>"
                       <?= $loggedIn ? 'readonly' : '' ?>>
            </label>
        <?php endif; ?>

        <?php if ($isOn('phone')): ?>
            <label><?= e($regCatalog['phone']['label']) ?>
                <input name="phone" <?= $isReq('phone') ? 'required' : '' ?> autocomplete="tel"
                       value="<?= e($val('phone', $uPhone)) ?>">
            </label>
        <?php endif; ?>

        <?php if ($isOn('curp')): ?>
            <label><?= e($regCatalog['curp']['label']) ?>
                <input name="curp" maxlength="18" <?= $isReq('curp') ? 'required' : '' ?>
                       value="<?= e($val('curp')) ?>"
                       placeholder="18 caracteres" style="text-transform:uppercase">
            </label>
        <?php endif; ?>

        <?php if ($isOn('birth_date')): ?>
            <label><?= e($regCatalog['birth_date']['label']) ?>
                <input type="date" name="birth_date" <?= $isReq('birth_date') ? 'required' : '' ?>
                       value="<?= e($val('birth_date')) ?>">
            </label>
        <?php endif; ?>

        <?php if ($isOn('sex')): ?>
            <label><?= e($regCatalog['sex']['label']) ?>
                <select name="sex" <?= $isReq('sex') ? 'required' : '' ?>>
                    <option value="">—</option>
                    <option value="F" <?= $val('sex') === 'F' ? 'selected' : '' ?>>Femenino</option>
                    <option value="M" <?= $val('sex') === 'M' ? 'selected' : '' ?>>Masculino</option>
                </select>
            </label>
        <?php endif; ?>

        <?php if ($isOn('nationality')): ?>
            <label><?= e($regCatalog['nationality']['label']) ?>
                <input name="nationality" maxlength="40" <?= $isReq('nationality') ? 'required' : '' ?>
                       value="<?= e($val('nationality', 'MEX')) ?>">
            </label>
        <?php endif; ?>

        <?php if ($isOn('exam_date')): ?>
            <label><?= e($regCatalog['exam_date']['label']) ?>
                <input type="date" name="exam_date" <?= $isReq('exam_date') ? 'required' : '' ?>
                       value="<?= e($val('exam_date')) ?>">
            </label>
        <?php endif; ?>

        <?php if ($isOn('exam_time')): ?>
            <label class="field-wide"><?= e($regCatalog['exam_time']['label']) ?>
                <span class="muted" style="font-weight:500;display:block;margin:0.15rem 0 0.35rem">
                    Horario regular: <?= e($schedule['time_start']) ?> – <?= e($schedule['time_end']) ?>
                </span>
                <select name="exam_time" id="examTimeSelect" <?= $isReq('exam_time') ? 'required' : '' ?>>
                    <option value="">— Elige hora —</option>
                    <?php foreach ($timeSlots as $slot): ?>
                        <option value="<?= e($slot) ?>" <?= $val('exam_time') === $slot ? 'selected' : '' ?>><?= e($slot) ?></option>
                    <?php endforeach; ?>
                    <?php if (!empty($schedule['extraordinary_enabled'])): ?>
                        <option value="__extraordinary__" <?= $val('exam_time_mode') === 'extraordinary' ? 'selected' : '' ?>>
                            Fuera de horario<?= $extraFee > 0 ? ' (+' . e(\App\Support\Str::money($extraFee, 'MXN')) . ')' : '' ?>
                        </option>
                    <?php endif; ?>
                </select>
            </label>
            <?php if (!empty($schedule['extraordinary_enabled'])): ?>
                <div id="extraordinaryBox" class="field-wide extraordinary-box" hidden>
                    <p class="alert alert-error" style="margin-top:0"><?= e($schedule['extraordinary_warning']) ?></p>
                    <?php if ($extraFee > 0): ?>
                        <p class="muted">Se sumará <strong><?= e(\App\Support\Str::money($extraFee, 'MXN')) ?></strong> al cobro SPEI (certificación + aplicación extraordinaria).</p>
                    <?php endif; ?>
                    <label>Hora fuera de rango
                        <input type="time" name="exam_time_extraordinary" id="examTimeExtra"
                               value="<?= e($val('exam_time_extraordinary')) ?>">
                    </label>
                    <label class="check">
                        <input type="checkbox" name="accept_extraordinary" value="1" <?= !empty($old['accept_extraordinary']) ? 'checked' : '' ?>>
                        Entiendo el costo extra y quiero presentar fuera de horario
                    </label>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php foreach ($regCustom as $cf): ?>
            <?php
            $ck = $cf['key'];
            $req = ($cf['mode'] ?? '') === 'required';
            $cval = (string) ($extraOld[$ck] ?? '');
            ?>
            <label class="<?= ($cf['type'] ?? '') === 'textarea' ? 'field-wide' : '' ?>">
                <?= e($cf['label']) ?>
                <?php if (($cf['type'] ?? 'text') === 'textarea'): ?>
                    <textarea name="extra[<?= e($ck) ?>]" rows="3" <?= $req ? 'required' : '' ?>><?= e($cval) ?></textarea>
                <?php else: ?>
                    <input type="<?= e($cf['type'] ?? 'text') ?>" name="extra[<?= e($ck) ?>]"
                           value="<?= e($cval) ?>" <?= $req ? 'required' : '' ?>>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>

        <div class="actions" style="grid-column:1/-1">
            <button class="btn" type="submit"><?= $loggedIn ? 'Continuar con mi solicitud' : 'Enviar solicitud' ?></button>
        </div>
    </form>
</section>

<script>
(function () {
  var sel = document.getElementById('examTimeSelect');
  var box = document.getElementById('extraordinaryBox');
  var extra = document.getElementById('examTimeExtra');
  if (!sel || !box) return;
  function sync() {
    var isExtra = sel.value === '__extraordinary__';
    box.hidden = !isExtra;
    if (extra) extra.required = isExtra;
  }
  sel.addEventListener('change', sync);
  sync();
})();
</script>

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
$scheduleSummary = \App\Catalog\CatalogRepository::examScheduleSummary($schedule);
$examFeatures = \App\Catalog\CatalogRepository::examProductFeatures(
    $item['features_json'] ?? null,
    (string) ($item['modality'] ?? 'online')
);
$examSittings = is_array($exam_sittings ?? null) ? $exam_sittings : [];
$schedulingMode = $examFeatures['mode'];
$initialDate = (string) (($old['exam_date'] ?? '') ?: '');
$timeSlots = $initialDate !== ''
    ? \App\Catalog\CatalogRepository::examTimeSlotsForDate($initialDate, $schedule)
    : [];
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
$minAhead = (int) ($examFeatures['min_days_ahead'] ?? 0);
$minDate = $minAhead > 0 ? (new DateTimeImmutable('today'))->modify('+' . $minAhead . ' days')->format('Y-m-d') : '';
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
            <?php
            $modLabel = \App\Catalog\CatalogRepository::modalities()[$item['modality'] ?? ''] ?? null;
            if ($modLabel): ?>
                · Modalidad: <strong><?= e($modLabel) ?></strong>
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

    <form method="post" action="/adquirir" class="stack form-grid" id="acquireForm" enctype="multipart/form-data">
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

        <?php if (!empty($examFeatures['requires_id_doc'])): ?>
            <div class="field-wide note" style="grid-column:1/-1">
                <h3 style="margin-top:0">Agenda después de tus documentos</h3>
                <p class="muted" style="margin-bottom:0">
                    Tras el registro firmarás el <strong>reglamento</strong> y subirás tu
                    <strong>INE escaneada por ambos lados en un solo PDF</strong>
                    (o pasaporte en PDF). <strong>Sin la identificación no se puede agendar</strong>
                    la fecha del examen; lo harás en tu ficha de alumno.
                </p>
            </div>
        <?php elseif ($schedulingMode === 'exam_sittings'): ?>
            <div class="field-wide note" style="grid-column:1/-1">
                <h3 style="margin-top:0">Fecha de aplicación</h3>
                <p class="muted">
                    Los exámenes presenciales (digital o papel) se aplican en sábados con fechas publicadas por el proveedor.
                    “Online” o en computadora no implica que puedas presentarlo desde casa: la modalidad presencial digital
                    se aplica en sede con supervisor.
                </p>
                <?php if ($examSittings !== []): ?>
                    <label>Elige una fecha disponible
                        <select name="exam_sitting_id" id="examSittingSelect">
                            <option value="">— Selecciona —</option>
                            <?php foreach ($examSittings as $sit): ?>
                                <?php
                                $sitLabel = $sit['exam_date']
                                    . (!empty($sit['exam_time']) ? ' · ' . $sit['exam_time'] : '')
                                    . ' (inscripción hasta ' . $sit['registration_deadline'] . ')'
                                    . (!empty($sit['label']) ? ' · ' . $sit['label'] : '')
                                    . (!empty($sit['venue_name']) ? ' · ' . $sit['venue_name'] : '');
                                ?>
                                <option value="<?= (int)$sit['id'] ?>" <?= $val('exam_sitting_id') === (string)$sit['id'] ? 'selected' : '' ?>>
                                    <?= e($sitLabel) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__later__" <?= $val('schedule_later') === '1' ? 'selected' : '' ?>>
                                Prefiero adquirir ahora y agendar cuando publiquen nuevas fechas
                            </option>
                        </select>
                    </label>
                <?php else: ?>
                    <p>No hay fechas publicadas todavía. Puedes adquirir la certificación y agendar cuando se publiquen.</p>
                    <input type="hidden" name="schedule_later" value="1">
                <?php endif; ?>
            </div>
        <?php elseif ($schedulingMode === 'flexible_home'): ?>
            <div class="field-wide note" style="grid-column:1/-1">
                <h3 style="margin-top:0">Fecha preferida (desde casa)</h3>
                <p class="muted">
                    Examen en línea <strong>desde casa</strong>, de lunes a viernes entre 9:00 y 18:00.
                    No hace falta elegir hora fija. Debes agendar con al menos <?= (int)$minAhead ?> días de antelación.
                </p>
                <label>Fecha preferida
                    <input type="date" name="exam_date" <?= $minDate !== '' ? 'min="' . e($minDate) . '"' : '' ?>
                           value="<?= e($val('exam_date')) ?>" required>
                </label>
            </div>
        <?php else: ?>
        <?php if ($isOn('exam_date')): ?>
            <label><?= e($regCatalog['exam_date']['label']) ?>
                <input type="date" name="exam_date" id="examDateInput" <?= $isReq('exam_date') ? 'required' : '' ?>
                       value="<?= e($val('exam_date')) ?>">
            </label>
        <?php endif; ?>

        <?php if ($isOn('exam_time')): ?>
            <label class="field-wide"><?= e($regCatalog['exam_time']['label']) ?>
                <span class="muted" style="font-weight:500;display:block;margin:0.15rem 0 0.35rem" id="examScheduleHint">
                    <?= e($scheduleSummary) ?>
                </span>
                <p class="muted" id="examDayHint" style="margin:0 0 0.35rem"></p>
                <select name="exam_time" id="examTimeSelect" <?= $isReq('exam_time') ? 'required' : '' ?>>
                    <option value="">— Primero elige la fecha —</option>
                    <?php foreach ($timeSlots as $slot): ?>
                        <option value="<?= e($slot) ?>" <?= $val('exam_time') === $slot ? 'selected' : '' ?>><?= e($slot) ?></option>
                    <?php endforeach; ?>
                    <?php if (!empty($schedule['extraordinary_enabled'])): ?>
                        <option value="__extraordinary__" <?= $val('exam_time_mode') === 'extraordinary' ? 'selected' : '' ?>>
                            Fuera de horario / día<?= $extraFee > 0 ? ' (+' . e(\App\Support\Str::money($extraFee, 'MXN')) . ')' : '' ?>
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
                    <label>Hora fuera de horario
                        <input type="time" name="exam_time_extraordinary" id="examTimeExtra"
                               value="<?= e($val('exam_time_extraordinary')) ?>">
                    </label>
                    <label class="check">
                        <input type="checkbox" name="accept_extraordinary" value="1" <?= !empty($old['accept_extraordinary']) ? 'checked' : '' ?>>
                        Entiendo el costo extra y quiero presentar fuera de horario
                    </label>
                </div>
            <?php else: ?>
                <p class="muted field-wide" id="noExtraHint">Esta certificación no admite aplicaciones extraordinarias.</p>
            <?php endif; ?>
            <script type="application/json" id="examScheduleJson"><?= json_encode([
                'slot_minutes' => (int) ($schedule['slot_minutes'] ?? 30),
                'extraordinary_enabled' => !empty($schedule['extraordinary_enabled']),
                'extraordinary_fee' => (float) ($schedule['extraordinary_fee'] ?? 0),
                'weekdays' => $schedule['weekdays'] ?? new \stdClass(),
                'labels' => \App\Catalog\CatalogRepository::weekdayLabels(),
                'selected' => $val('exam_time'),
                'selected_extra' => $val('exam_time_mode') === 'extraordinary',
            ], JSON_UNESCAPED_UNICODE) ?></script>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (empty($examFeatures['requires_id_doc']) && (!empty($examFeatures['requires_regulation_upload']))): ?>
            <div class="field-wide note" style="grid-column:1/-1">
                <h3 style="margin-top:0">Documentos</h3>
                <p class="muted" style="margin-bottom:0">
                    Después del registro firmarás el <strong>reglamento</strong> en tu ficha de alumno.
                </p>
            </div>
        <?php endif; ?>

        <?php foreach ($regCustom as $cf): ?>
            <?php
            $ck = $cf['key'];
            $req = ($cf['mode'] ?? '') === 'required';
            $cval = (string) ($extraOld[$ck] ?? '');
            $ctype = (string) ($cf['type'] ?? 'text');
            ?>
            <label class="<?= in_array($ctype, ['textarea', 'file'], true) ? 'field-wide' : '' ?>">
                <?= e($cf['label']) ?>
                <?php if ($ctype === 'textarea'): ?>
                    <textarea name="extra[<?= e($ck) ?>]" rows="3" <?= $req ? 'required' : '' ?>><?= e($cval) ?></textarea>
                <?php elseif ($ctype === 'file'): ?>
                    <input type="file" name="extra_file[<?= e($ck) ?>]"
                           accept=".pdf,.png,.jpg,.jpeg,.webp,application/pdf,image/*"
                        <?= $req ? 'required' : '' ?>>
                    <small class="muted">PDF o imagen. Se adjunta a tu caso y el admin/correo pueden usar el enlace.</small>
                <?php elseif ($ctype === 'url'): ?>
                    <input type="url" name="extra[<?= e($ck) ?>]" value="<?= e($cval) ?>"
                           placeholder="https://…" <?= $req ? 'required' : '' ?>>
                <?php else: ?>
                    <input type="<?= e(in_array($ctype, ['text','date','number','tel','email','time'], true) ? $ctype : 'text') ?>"
                           name="extra[<?= e($ck) ?>]"
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
  var dateInput = document.getElementById('examDateInput');
  var dayHint = document.getElementById('examDayHint');
  var cfgEl = document.getElementById('examScheduleJson');
  var cfg = null;
  try { cfg = cfgEl ? JSON.parse(cfgEl.textContent || '{}') : null; } catch (e) { cfg = null; }

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function weekdayN(ymd) {
    if (!ymd) return null;
    var p = ymd.split('-');
    if (p.length !== 3) return null;
    var d = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
    // JS: 0=Sun..6=Sat → ISO 1=Mon..7=Sun
    var js = d.getUTCDay();
    return js === 0 ? 7 : js;
  }
  function slotsForDay(day) {
    if (!day || !day.enabled) return [];
    if (day.kind === 'fixed') return Array.isArray(day.times) ? day.times.slice() : [];
    var start = (day.time_start || '09:00').slice(0, 5);
    var end = (day.time_end || '18:00').slice(0, 5);
    var step = Math.max(5, parseInt((cfg && cfg.slot_minutes) || 30, 10));
    var out = [];
    var sp = start.split(':').map(Number);
    var ep = end.split(':').map(Number);
    var cur = sp[0] * 60 + sp[1];
    var limit = ep[0] * 60 + ep[1];
    var guard = 0;
    while (cur <= limit && guard < 96) {
      out.push(pad(Math.floor(cur / 60)) + ':' + pad(cur % 60));
      cur += step;
      guard++;
    }
    return out;
  }
  function rebuildTimes() {
    if (!sel || !cfg) return;
    var ymd = dateInput ? dateInput.value : '';
    var n = weekdayN(ymd);
    var prev = sel.value;
    var wanted = (cfg.selected_extra && prev === '') ? '__extraordinary__' : (prev || cfg.selected || '');
    while (sel.options.length) sel.remove(0);
    sel.add(new Option(ymd ? '— Elige hora —' : '— Primero elige la fecha —', ''));
    if (!ymd || !n) {
      if (dayHint) dayHint.textContent = '';
      syncExtra();
      return;
    }
    var day = (cfg.weekdays && cfg.weekdays[String(n)]) || null;
    var label = (cfg.labels && cfg.labels[n]) || ('día ' + n);
    if (!day || !day.enabled) {
      if (dayHint) dayHint.textContent = label + ': no hay aplicaciones este día.';
      if (cfg.extraordinary_enabled) {
        sel.add(new Option('Fuera de horario / día', '__extraordinary__'));
      }
      if (wanted === '__extraordinary__') sel.value = '__extraordinary__';
      syncExtra();
      return;
    }
    var slots = slotsForDay(day);
    var detail = day.kind === 'fixed'
      ? ('horas fijas ' + slots.join(', '))
      : ((day.time_start || '') + ' – ' + (day.time_end || ''));
    if (dayHint) dayHint.textContent = label + ': ' + detail;
    slots.forEach(function (s) { sel.add(new Option(s, s)); });
    if (cfg.extraordinary_enabled) {
      sel.add(new Option('Fuera de horario / día', '__extraordinary__'));
    }
    if (wanted && [...sel.options].some(function (o) { return o.value === wanted; })) {
      sel.value = wanted;
    }
    syncExtra();
  }
  function syncExtra() {
    if (!sel || !box) return;
    var isExtra = sel.value === '__extraordinary__';
    box.hidden = !isExtra;
    if (extra) extra.required = isExtra;
  }
  sel?.addEventListener('change', syncExtra);
  dateInput?.addEventListener('change', function () {
    if (cfg) cfg.selected = '';
    rebuildTimes();
  });
  rebuildTimes();
})();
</script>

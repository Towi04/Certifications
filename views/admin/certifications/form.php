<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$linkedCourses = $linkedCourses ?? [];
$tiers = $tiers ?? [];
$tierPrices = $tierPrices ?? [];
$modalities = \App\Catalog\CatalogRepository::modalities();
$skillsCatalog = \App\Catalog\CatalogRepository::certificationSkills();
$cenniTypes = \App\Catalog\CatalogRepository::cenniDocTypes();
$relationTypes = \App\Catalog\CatalogRepository::courseRelationTypes();
$selectedSkills = [];
if (!empty($item['skills_json'])) {
    $decoded = is_string($item['skills_json'])
        ? json_decode($item['skills_json'], true)
        : $item['skills_json'];
    if (is_array($decoded)) {
        $selectedSkills = array_map('strval', $decoded);
    }
}
$scoreRanges = \App\Catalog\CatalogRepository::decodeScoreRanges($item['score_ranges_json'] ?? null);
if ($scoreRanges === [] && !empty($item['score_range'])) {
    // Compatibilidad: un solo texto antiguo como etiqueta
    $scoreRanges = [['min' => '', 'max' => '', 'label' => (string) $item['score_range']]];
}
if ($scoreRanges === []) {
    $scoreRanges = [['min' => '', 'max' => '', 'label' => '']];
}
$isLevel = !empty($item['is_level_exam']);
$cenniOn = !empty($item['cenni_eligible']);
$conocerOn = !empty($item['conocer_eligible']);
$cenniDoc = $item['cenni_doc_type'] ?? 'constancia';
if ($cenniDoc === 'certificado' || $cenniDoc === 'diploma') {
    $cenniDoc = 'constancia_certificado_diploma';
}
$modality = $item['modality'] ?? 'online';
if (!isset($modalities[$modality])) {
    $modality = 'online';
}
$published = !empty($item['is_published']);
$iconEye = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.8"/></svg>';
$iconEyeOff = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 3l18 18M10.5 10.6A3.2 3.2 0 0 0 13.4 13.5M9.9 5.2C10.6 5.1 11.3 5 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-4.2 4.8M6.1 6.1A17.4 17.4 0 0 0 2 12s3.5 7 10 7c1.3 0 2.5-.3 3.6-.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconEdit = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M13.5 6.5l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
?>
<section class="note certification-edit">
    <h2><?= e($title) ?></h2>
    <?php if ($item): ?>
        <p class="muted">
            Certificaciones de <strong><?= e($item['provider_name'] ?? '') ?></strong>
            · código <code><?= e($item['code']) ?></code>
            · slug <code><?= e($item['slug']) ?></code>
            <span class="admin-only-hint">(asignados automáticamente)</span>
        </p>
    <?php else: ?>
        <p class="muted">Preferible crearlas desde Proveedores → Certificaciones. Aquí el código y slug se generan solos.</p>
    <?php endif; ?>

    <form method="post" action="/admin/certifications/save" class="stack form-grid" id="certForm">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>

        <?php if (!$item): ?>
            <label>Proveedor
                <select name="provider_id" required>
                    <option value="">—</option>
                    <?php foreach ($providers as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>

        <label>Protocolo
            <select name="protocol_id">
                <option value="">—</option>
                <?php foreach ($protocols as $pr): ?>
                    <option value="<?= (int)$pr['id'] ?>" <?= (int)($item['protocol_id'] ?? 0) === (int)$pr['id'] ? 'selected' : '' ?>><?= e($pr['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nombre<input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
        <label>Modalidad
            <select name="modality">
                <?php foreach ($modalities as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $modality === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="field-wide html-field" data-html-field>
            <div class="html-field-head">
                <span class="html-field-title">Resumen (HTML)</span>
                <button type="button" class="icon-btn html-preview-toggle" title="Vista previa / código" aria-label="Alternar vista previa HTML" aria-pressed="false">&lt;/&gt;</button>
            </div>
            <textarea name="short_description" rows="14" class="html-editor" placeholder="Puedes usar HTML: &lt;p&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;ul&gt;&lt;li&gt;…"><?= e($item['short_description'] ?? '') ?></textarea>
            <div class="html-preview prose" hidden></div>
        </div>

        <?php
        $valuePointsText = implode("\n", \App\Catalog\CatalogRepository::decodeValuePoints($item['value_points_json'] ?? null));
        ?>
        <label class="field-wide">Por qué con Instituto Doceo (valor agregado)
            <span class="muted" style="font-weight:400;display:block;margin:0.2rem 0 0.45rem">
                Una ventaja por línea. Se muestra destacado en la ficha (antes de la descripción).
                Ej.: Aplicación de lunes a sábado · Supervisión Doceo · Acompañamiento CENNI
            </span>
            <textarea name="value_points" rows="6" placeholder="Aplicamos de lunes a sábado&#10;Supervisión durante el examen&#10;Acompañamiento en trámite CENNI"><?= e($valuePointsText) ?></textarea>
        </label>

        <div class="field-wide html-field html-field--long" data-html-field>
            <div class="html-field-head">
                <span class="html-field-title">Descripción (HTML)</span>
                <button type="button" class="icon-btn html-preview-toggle" title="Vista previa / código" aria-label="Alternar vista previa HTML" aria-pressed="false">&lt;/&gt;</button>
            </div>
            <textarea name="description_html" rows="20" class="html-editor" placeholder="Descripción larga con HTML"><?= e($item['description_html'] ?? '') ?></textarea>
            <div class="html-preview prose" hidden></div>
        </div>

        <label>Duración<input name="duration_label" value="<?= e($item['duration_label'] ?? '') ?>" placeholder="Ej. 2 h 30 min"></label>
        <label>Audiencia<input name="audience" value="<?= e($item['audience'] ?? '') ?>"></label>

        <label class="check field-wide">
            <input type="checkbox" name="is_level_exam" id="isLevelExam" <?= $isLevel ? 'checked' : '' ?>>
            Es un examen de nivel (evalúa habilidades)
        </label>
        <fieldset class="field-wide skills-fieldset" id="skillsFieldset" <?= $isLevel ? '' : 'hidden' ?>>
            <legend>Habilidades que evalúa</legend>
            <div class="skills-grid">
                <?php foreach ($skillsCatalog as $key => $label): ?>
                    <?php $skillSvg = \App\Support\CertIcons::skillSvg($key); ?>
                    <label class="skill-icon-toggle" title="<?= e($label) ?>">
                        <input type="checkbox" name="skills[]" value="<?= e($key) ?>" <?= in_array($key, $selectedSkills, true) ? 'checked' : '' ?>>
                        <span class="skill-icon-face" aria-label="<?= e($label) ?>"><?= $skillSvg ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="field-wide score-ranges-fieldset">
            <legend>Rangos de puntaje / nivel</legend>
            <p class="muted score-ranges-hint">Agrega varios rangos, por ejemplo 16–25 = Nivel A1 o 700–1000 = Aprobado.</p>
            <div id="scoreRangesList" class="score-ranges-list">
                <?php foreach ($scoreRanges as $i => $range): ?>
                    <div class="score-range-row" data-score-row>
                        <label>Desde
                            <input name="score_ranges[<?= (int)$i ?>][min]" value="<?= e($range['min']) ?>" placeholder="0" inputmode="decimal">
                        </label>
                        <label>Hasta
                            <input name="score_ranges[<?= (int)$i ?>][max]" value="<?= e($range['max']) ?>" placeholder="100" inputmode="decimal">
                        </label>
                        <label class="score-range-label">Resultado / nivel
                            <input name="score_ranges[<?= (int)$i ?>][label]" value="<?= e($range['label']) ?>" placeholder="Nivel A1 / Aprobado / Banda 1">
                        </label>
                        <button type="button" class="icon-btn score-range-remove" title="Quitar rango" aria-label="Quitar rango">×</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost" id="addScoreRange">+ Agregar rango</button>
        </fieldset>

        <label>Precio público (MXN)
            <input type="number" step="0.01" name="public_price" value="<?= e((string)($item['public_price'] ?? '')) ?>">
        </label>
        <label>Costo Doceo (MXN)
            <input type="number" step="0.01" name="cost_price" value="<?= e((string)($item['cost_price'] ?? '')) ?>" placeholder="Lo que nos cuesta">
            <small class="muted">Costo interno; no se muestra a partners.</small>
        </label>

        <fieldset class="field-wide tier-prices-fieldset">
            <legend>Precios por nivel TR (MXN)</legend>
            <p class="muted">Al crear un nivel nuevo en Niveles TR, aparecerá aquí automáticamente en todas las certificaciones.</p>
            <?php if ($tiers): ?>
                <div class="tier-prices-grid">
                    <?php foreach ($tiers as $tier): ?>
                        <?php $tid = (int) $tier['id']; ?>
                        <label><?= e($tier['name']) ?>
                            <input type="number" step="0.01" name="tier_prices[<?= $tid ?>]"
                                   value="<?= e(isset($tierPrices[$tid]) ? (string) $tierPrices[$tid] : '') ?>"
                                   placeholder="Sin precio">
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted">Aún no hay niveles TR activos. Créalos en Admin → Niveles TR.</p>
            <?php endif; ?>
        </fieldset>

        <fieldset class="field-wide">
            <legend>Campos del formulario de adquisición</legend>
            <p class="muted">
                Activa u oculta campos built-in, define horario de examen y agrega campos nuevos
                (dirección, etc.) cuando una certificadora lo pida.
            </p>
            <?php
            $regConfig = \App\Catalog\CatalogRepository::decodeRegistrationConfig($item['registration_fields_json'] ?? null);
            $regFields = $regConfig['modes'];
            $regCatalog = \App\Catalog\CatalogRepository::registrationFieldCatalog();
            $regCustom = $regConfig['custom'];
            $schedule = $regConfig['schedule'];
            $modeLabels = ['off' => 'No pedir', 'optional' => 'Opcional', 'required' => 'Obligatorio'];
            ?>
            <h3 class="reg-subtitle">Campos base</h3>
            <div class="reg-fields-grid">
                <?php foreach ($regCatalog as $key => $meta): ?>
                    <?php
                    $mode = $regFields[$key] ?? ($meta['default'] ?? 'off');
                    $locked = !empty($meta['locked']);
                    ?>
                    <label class="reg-field-row">
                        <span><?= e($meta['label']) ?><?= $locked ? ' <em class="muted">(fijo)</em>' : '' ?></span>
                        <?php if ($locked): ?>
                            <input type="hidden" name="registration_fields[<?= e($key) ?>]" value="required">
                            <select disabled>
                                <option selected>Obligatorio</option>
                            </select>
                        <?php else: ?>
                            <select name="registration_fields[<?= e($key) ?>]">
                                <?php foreach ($modeLabels as $val => $lab): ?>
                                    <option value="<?= e($val) ?>" <?= $mode === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <h3 class="reg-subtitle">Horario de examen por día</h3>
            <p class="muted">
                Define rango o horas fijas según el día (ej. UKS entre semana vs sábado, TOEFL solo sábados).
                Aplica cuando “Hora preferida de examen” no está en “No pedir”.
            </p>
            <div class="actions" style="margin-bottom:0.75rem;flex-wrap:wrap;gap:0.5rem">
                <button type="button" class="btn btn-ghost" data-schedule-preset="uks">Preset UKS</button>
                <button type="button" class="btn btn-ghost" data-schedule-preset="itep">Preset iTEP (igual todos los días)</button>
                <button type="button" class="btn btn-ghost" data-schedule-preset="toefl">Preset TOEFL (sábados fijos)</button>
            </div>
            <div class="reg-fields-grid">
                <label class="reg-field-row">Intervalo (modo rango)
                    <select name="exam_slot_minutes">
                        <?php foreach ([15 => '15 min', 30 => '30 min', 60 => '60 min'] as $m => $lab): ?>
                            <option value="<?= $m ?>" <?= (int)$schedule['slot_minutes'] === $m ? 'selected' : '' ?>><?= e($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="check reg-field-row">
                    <input type="checkbox" name="exam_extraordinary_enabled" value="1" <?= !empty($schedule['extraordinary_enabled']) ? 'checked' : '' ?>>
                    Permitir aplicación extraordinaria (fuera de horario / día)
                </label>
                <label class="reg-field-row">Costo aplicación extraordinaria (MXN)
                    <input type="number" step="0.01" min="0" name="exam_extraordinary_fee"
                           value="<?= e((string)$schedule['extraordinary_fee']) ?>">
                </label>
                <label class="reg-field-row field-wide">Advertencia al alumno
                    <textarea name="exam_extraordinary_warning" rows="2"><?= e($schedule['extraordinary_warning']) ?></textarea>
                </label>
            </div>
            <?php
            $weekdayLabels = \App\Catalog\CatalogRepository::weekdayLabels();
            $weekdays = is_array($schedule['weekdays'] ?? null) ? $schedule['weekdays'] : [];
            ?>
            <div class="table-wrap" style="margin-top:0.75rem">
                <table class="data-table" id="examWeekdayTable">
                    <thead>
                        <tr>
                            <th>Día</th>
                            <th>Abierto</th>
                            <th>Tipo</th>
                            <th>Desde / Hasta (rango)</th>
                            <th>Horas fijas</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($weekdayLabels as $n => $label): ?>
                        <?php
                        $day = $weekdays[(string) $n] ?? [
                            'enabled' => false,
                            'kind' => 'range',
                            'time_start' => $schedule['time_start'] ?? '09:00',
                            'time_end' => $schedule['time_end'] ?? '18:00',
                            'times' => [],
                        ];
                        $kind = ($day['kind'] ?? 'range') === 'fixed' ? 'fixed' : 'range';
                        $timesStr = implode(', ', $day['times'] ?? []);
                        ?>
                        <tr data-weekday-row="<?= (int)$n ?>">
                            <td><strong><?= e($label) ?></strong></td>
                            <td>
                                <label class="check">
                                    <input type="checkbox" name="exam_weekday[<?= (int)$n ?>][enabled]" value="1"
                                           <?= !empty($day['enabled']) ? 'checked' : '' ?> data-day-enabled>
                                </label>
                            </td>
                            <td>
                                <select name="exam_weekday[<?= (int)$n ?>][kind]" data-day-kind>
                                    <option value="range" <?= $kind === 'range' ? 'selected' : '' ?>>Rango</option>
                                    <option value="fixed" <?= $kind === 'fixed' ? 'selected' : '' ?>>Horas fijas</option>
                                </select>
                            </td>
                            <td>
                                <div class="inline-times" data-range-fields>
                                    <input type="time" name="exam_weekday[<?= (int)$n ?>][time_start]"
                                           value="<?= e((string)($day['time_start'] ?? '09:00')) ?>">
                                    <span>–</span>
                                    <input type="time" name="exam_weekday[<?= (int)$n ?>][time_end]"
                                           value="<?= e((string)($day['time_end'] ?? '18:00')) ?>">
                                </div>
                            </td>
                            <td>
                                <input type="text" name="exam_weekday[<?= (int)$n ?>][times]" data-fixed-fields
                                       value="<?= e($timesStr) ?>"
                                       placeholder="11:00, 13:00" <?= $kind === 'fixed' ? '' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <input type="hidden" name="exam_time_start" value="<?= e($schedule['time_start'] ?? '09:00') ?>">
            <input type="hidden" name="exam_time_end" value="<?= e($schedule['time_end'] ?? '18:00') ?>">
            <p class="muted" style="margin-top:0.5rem">
                En horas fijas escribe HH:MM separadas por coma. Presets: UKS lun–vie 10:00–17:30 y sáb 08:00–12:00 sin extraordinarias;
                iTEP mismo rango todos los días; TOEFL solo sábado 11:00 y 13:00 con extraordinarias.
            </p>

            <h3 class="reg-subtitle">Campos personalizados</h3>
            <p class="muted">Agrega campos nuevos o elimínalos si ya no aplican. No afecta los campos base (esos se ocultan con “No pedir”).</p>
            <div id="customFieldsList" class="custom-fields-list">
                <?php foreach ($regCustom as $i => $cf): ?>
                    <div class="custom-field-row" data-custom-row>
                        <input type="hidden" name="custom_fields[<?= (int)$i ?>][key]" value="<?= e($cf['key']) ?>">
                        <label>Etiqueta
                            <input name="custom_fields[<?= (int)$i ?>][label]" required value="<?= e($cf['label']) ?>">
                        </label>
                        <label>Tipo
                            <select name="custom_fields[<?= (int)$i ?>][type]">
                                <?php foreach (['text' => 'Texto', 'textarea' => 'Texto largo', 'date' => 'Fecha', 'number' => 'Número', 'tel' => 'Teléfono', 'email' => 'Correo'] as $tv => $tl): ?>
                                    <option value="<?= e($tv) ?>" <?= ($cf['type'] ?? '') === $tv ? 'selected' : '' ?>><?= e($tl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Uso
                            <select name="custom_fields[<?= (int)$i ?>][mode]">
                                <?php foreach (['optional' => 'Opcional', 'required' => 'Obligatorio'] as $mv => $ml): ?>
                                    <option value="<?= e($mv) ?>" <?= ($cf['mode'] ?? '') === $mv ? 'selected' : '' ?>><?= e($ml) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="check">
                            <input type="checkbox" name="custom_fields[<?= (int)$i ?>][delete]" value="1"> Eliminar
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="actions" style="margin-top:0.75rem">
                <button class="btn btn-ghost" type="button" id="addCustomFieldBtn">Agregar campo</button>
            </div>
            <template id="customFieldTemplate">
                <div class="custom-field-row" data-custom-row>
                    <input type="hidden" name="custom_fields[__i__][key]" value="">
                    <label>Etiqueta
                        <input name="custom_fields[__i__][label]" required placeholder="Ej. Dirección">
                    </label>
                    <label>Tipo
                        <select name="custom_fields[__i__][type]">
                            <option value="text">Texto</option>
                            <option value="textarea">Texto largo</option>
                            <option value="date">Fecha</option>
                            <option value="number">Número</option>
                            <option value="tel">Teléfono</option>
                            <option value="email">Correo</option>
                        </select>
                    </label>
                    <label>Uso
                        <select name="custom_fields[__i__][mode]">
                            <option value="optional">Opcional</option>
                            <option value="required">Obligatorio</option>
                        </select>
                    </label>
                    <label class="check">
                        <input type="checkbox" name="custom_fields[__i__][delete]" value="1"> Eliminar
                    </label>
                </div>
            </template>
            <script>
            (function () {
              var list = document.getElementById('customFieldsList');
              var btn = document.getElementById('addCustomFieldBtn');
              var tpl = document.getElementById('customFieldTemplate');
              if (!list || !btn || !tpl) return;
              var idx = list.querySelectorAll('[data-custom-row]').length;
              btn.addEventListener('click', function () {
                var html = tpl.innerHTML.replaceAll('__i__', String(idx++));
                var wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                list.appendChild(wrap.firstElementChild);
              });
            })();
            </script>
        </fieldset>

        <div class="eligibility-row">
            <label class="check">
                <input type="checkbox" name="cenni_eligible" id="cenniEligible" <?= $cenniOn ? 'checked' : '' ?>>
                Elegible CENNI
            </label>
            <div id="cenniFields" class="eligibility-fields" <?= $cenniOn ? '' : 'hidden' ?>>
                <label>Tipo de documento
                    <select name="cenni_doc_type">
                        <?php foreach ($cenniTypes as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $cenniDoc === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="fee-field">Fee CENNI
                    <input type="number" step="0.01" name="cenni_fee" value="<?= e((string)($item['cenni_fee'] ?? '0')) ?>">
                </label>
                <label>Proceso CENNI
                    <select name="cenni_process">
                        <?php
                        $cenniProc = (string) ($item['cenni_process'] ?? 'doceo_managed');
                        $procOpts = [
                            'doceo_managed' => 'Doceo gestiona (alumno sube docs aquí)',
                            'uks_external' => 'UKS externo (ELET: alumno sube en UKS)',
                            'none' => 'Sin trámite',
                        ];
                        foreach ($procOpts as $val => $lab):
                        ?>
                            <option value="<?= e($val) ?>" <?= $cenniProc === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </div>

        <div class="eligibility-row">
            <label class="check">
                <input type="checkbox" name="conocer_eligible" id="conocerEligible" <?= $conocerOn ? 'checked' : '' ?>>
                Elegible CONOCER
            </label>
            <div id="conocerFields" class="eligibility-fields" <?= $conocerOn ? '' : 'hidden' ?>>
                <label class="fee-field">Fee CONOCER
                    <input type="number" step="0.01" name="conocer_fee" value="<?= e((string)($item['conocer_fee'] ?? '0')) ?>">
                </label>
            </div>
        </div>

        <label>Orden<input type="number" name="sort_order" value="<?= e((string)($item['sort_order'] ?? '0')) ?>"></label>
        <label class="check">
            <input type="checkbox" name="is_featured" <?= !empty($item['is_featured']) ? 'checked' : '' ?>>
            Producto estrella (aparece arriba en la vitrina pública)
        </label>

        <div class="actions">
            <button class="btn" type="submit" name="intent" value="save">Guardar</button>
            <?php if ($item && !$published): ?>
                <button class="btn" type="submit" name="intent" value="publish">Publicar</button>
            <?php elseif ($item && $published): ?>
                <span class="pill pill-ok">Publicada</span>
                <span class="muted">Para ocultarla usa el ojo en el listado.</span>
            <?php endif; ?>
            <a class="btn btn-ghost" href="/admin/certifications">Volver</a>
        </div>
    </form>

    <?php if ($item): ?>
        <h3>Cursos vinculados</h3>
        <?php if ($linkedCourses): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Curso</th><th>Relación</th><th>Precio</th><th>Plataforma</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($linkedCourses as $c): ?>
                        <tr>
                            <td><?= e($c['course_name']) ?></td>
                            <td><?= e($relationTypes[$c['relation_type']] ?? $c['relation_type']) ?></td>
                            <td>
                                <?php if ($c['bundle_price'] !== null && in_array(($c['relation_type'] ?? ''), ['bundle_discount', 'sold_separate'], true)): ?>
                                    <?= e(\App\Support\Str::money((float)$c['bundle_price'])) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= e($c['platform_type']) ?></td>
                            <td>
                                <form method="post" action="/admin/certifications/detach-course" class="inline-form">
                                    <input type="hidden" name="certification_id" value="<?= (int)$item['id'] ?>">
                                    <input type="hidden" name="course_id" value="<?= (int)$c['course_id'] ?>">
                                    <button type="submit" class="linkish">Quitar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">Ningún curso vinculado aún.</p>
        <?php endif; ?>

        <form method="post" action="/admin/certifications/attach-course" class="stack form-grid" style="margin-top:1rem" id="attachCourseForm">
            <input type="hidden" name="certification_id" value="<?= (int)$item['id'] ?>">
            <label>Vincular curso
                <select name="course_id" required>
                    <option value="">—</option>
                    <?php foreach (($courses ?? []) as $course): ?>
                        <option value="<?= (int)$course['id'] ?>"><?= e($course['name']) ?> (<?= e($course['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Relación
                <select name="relation_type" id="relationType">
                    <?php foreach ($relationTypes as $value => $label): ?>
                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label id="bundlePriceField" style="display:none">Precio
                <input type="number" step="0.01" min="0" name="bundle_price">
                <small class="muted" id="bundlePriceHint">Requerido si se vende por separado. Opcional en bundle.</small>
            </label>
            <label>Notas<input name="notes"></label>
            <div class="actions"><button class="btn" type="submit">Vincular curso</button></div>
        </form>
    <?php endif; ?>
</section>

<script>
(() => {
  const level = document.getElementById('isLevelExam');
  const skills = document.getElementById('skillsFieldset');
  const syncLevel = () => { if (skills) skills.hidden = !level?.checked; };
  level?.addEventListener('change', syncLevel);
  syncLevel();

  const cenni = document.getElementById('cenniEligible');
  const cenniFields = document.getElementById('cenniFields');
  const syncCenni = () => { if (cenniFields) cenniFields.hidden = !cenni?.checked; };
  cenni?.addEventListener('change', syncCenni);
  syncCenni();

  const conocer = document.getElementById('conocerEligible');
  const conocerFields = document.getElementById('conocerFields');
  const syncConocer = () => { if (conocerFields) conocerFields.hidden = !conocer?.checked; };
  conocer?.addEventListener('change', syncConocer);
  syncConocer();

  const rel = document.getElementById('relationType');
  const bundle = document.getElementById('bundlePriceField');
  const hint = document.getElementById('bundlePriceHint');
  const syncRel = () => {
    if (!bundle || !rel) return;
    const needs = rel.value === 'sold_separate' || rel.value === 'bundle_discount';
    bundle.style.display = needs ? '' : 'none';
    if (hint) {
      hint.textContent = rel.value === 'sold_separate'
        ? 'Requerido: precio de venta del curso.'
        : 'Opcional: precio del bundle con descuento.';
    }
  };
  rel?.addEventListener('change', syncRel);
  syncRel();

  const list = document.getElementById('scoreRangesList');
  const addBtn = document.getElementById('addScoreRange');
  const reindexRows = () => {
    if (!list) return;
    [...list.querySelectorAll('[data-score-row]')].forEach((row, i) => {
      row.querySelectorAll('input').forEach((input) => {
        const field = (input.getAttribute('name') || '').match(/\[(min|max|label)\]$/);
        if (field) input.setAttribute('name', `score_ranges[${i}][${field[1]}]`);
      });
    });
  };
  const bindRemove = (row) => {
    row.querySelector('.score-range-remove')?.addEventListener('click', () => {
      const rows = list?.querySelectorAll('[data-score-row]') || [];
      if (rows.length <= 1) {
        row.querySelectorAll('input').forEach((input) => { input.value = ''; });
        return;
      }
      row.remove();
      reindexRows();
    });
  };
  list?.querySelectorAll('[data-score-row]').forEach(bindRemove);
  addBtn?.addEventListener('click', () => {
    if (!list) return;
    const i = list.querySelectorAll('[data-score-row]').length;
    const row = document.createElement('div');
    row.className = 'score-range-row';
    row.setAttribute('data-score-row', '');
    row.innerHTML = `
      <label>Desde
        <input name="score_ranges[${i}][min]" value="" placeholder="0" inputmode="decimal">
      </label>
      <label>Hasta
        <input name="score_ranges[${i}][max]" value="" placeholder="100" inputmode="decimal">
      </label>
      <label class="score-range-label">Resultado / nivel
        <input name="score_ranges[${i}][label]" value="" placeholder="Nivel A1 / Aprobado / Banda 1">
      </label>
      <button type="button" class="icon-btn score-range-remove" title="Quitar rango" aria-label="Quitar rango">×</button>
    `;
    list.appendChild(row);
    bindRemove(row);
  });

  document.querySelectorAll('[data-html-field]').forEach((wrap) => {
    const btn = wrap.querySelector('.html-preview-toggle');
    const ta = wrap.querySelector('textarea.html-editor');
    const preview = wrap.querySelector('.html-preview');
    if (!btn || !ta || !preview) return;
    btn.addEventListener('click', () => {
      const showing = btn.getAttribute('aria-pressed') === 'true';
      if (showing) {
        preview.hidden = true;
        preview.innerHTML = '';
        ta.hidden = false;
        btn.setAttribute('aria-pressed', 'false');
        btn.classList.remove('is-active');
        btn.title = 'Vista previa';
      } else {
        preview.innerHTML = ta.value.trim() !== '' ? ta.value : '<p class="muted"><em>(Vacío)</em></p>';
        ta.hidden = true;
        preview.hidden = false;
        btn.setAttribute('aria-pressed', 'true');
        btn.classList.add('is-active');
        btn.title = 'Ver código HTML';
      }
    });
  });

  const weekdayTable = document.getElementById('examWeekdayTable');
  const syncDayRow = (row) => {
    const kind = row.querySelector('[data-day-kind]')?.value || 'range';
    const range = row.querySelector('[data-range-fields]');
    const fixed = row.querySelector('[data-fixed-fields]');
    if (range) range.style.opacity = kind === 'range' ? '1' : '0.35';
    if (fixed) fixed.style.opacity = kind === 'fixed' ? '1' : '0.35';
  };
  weekdayTable?.querySelectorAll('[data-weekday-row]').forEach((row) => {
    row.querySelector('[data-day-kind]')?.addEventListener('change', () => syncDayRow(row));
    syncDayRow(row);
  });
  const setDay = (n, cfg) => {
    const row = weekdayTable?.querySelector(`[data-weekday-row="${n}"]`);
    if (!row) return;
    const en = row.querySelector('[data-day-enabled]');
    const kind = row.querySelector('[data-day-kind]');
    const start = row.querySelector('input[name$="[time_start]"]');
    const end = row.querySelector('input[name$="[time_end]"]');
    const times = row.querySelector('[data-fixed-fields]');
    if (en) en.checked = !!cfg.enabled;
    if (kind) kind.value = cfg.kind || 'range';
    if (start && cfg.time_start) start.value = cfg.time_start;
    if (end && cfg.time_end) end.value = cfg.time_end;
    if (times) times.value = cfg.times || '';
    syncDayRow(row);
  };
  const setExtra = (enabled, fee, warn) => {
    const cb = document.querySelector('input[name="exam_extraordinary_enabled"]');
    const feeEl = document.querySelector('input[name="exam_extraordinary_fee"]');
    const warnEl = document.querySelector('textarea[name="exam_extraordinary_warning"]');
    if (cb) cb.checked = !!enabled;
    if (feeEl && fee !== undefined) feeEl.value = String(fee);
    if (warnEl && warn) warnEl.value = warn;
  };
  document.querySelectorAll('[data-schedule-preset]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const preset = btn.getAttribute('data-schedule-preset');
      if (preset === 'uks') {
        for (let d = 1; d <= 5; d++) {
          setDay(d, { enabled: true, kind: 'range', time_start: '10:00', time_end: '17:30', times: '' });
        }
        setDay(6, { enabled: true, kind: 'range', time_start: '08:00', time_end: '12:00', times: '' });
        setDay(7, { enabled: false, kind: 'range', times: '' });
        setExtra(false, 0, 'Esta certificación no admite aplicaciones extraordinarias.');
      } else if (preset === 'itep') {
        for (let d = 1; d <= 7; d++) {
          setDay(d, { enabled: true, kind: 'range', time_start: '09:00', time_end: '18:00', times: '' });
        }
        setExtra(false, 0, 'Esta certificación no admite aplicaciones extraordinarias.');
      } else if (preset === 'toefl') {
        for (let d = 1; d <= 5; d++) {
          setDay(d, { enabled: false, kind: 'range', times: '' });
        }
        setDay(6, { enabled: true, kind: 'fixed', times: '11:00, 13:00' });
        setDay(7, { enabled: false, kind: 'range', times: '' });
        setExtra(true, 0, 'Si necesitas otro día u hora, elige aplicación extraordinaria (puede tener costo extra).');
      }
    });
  });
})();
</script>

<?php if ($item): ?>
<?php
$assets = $assets ?? [];
$assetTypes = $assetTypes ?? \App\Catalog\CatalogRepository::assetTypesFor('certification');
$ownerType = 'certification';
$ownerId = (int) $item['id'];
$redirect = '/admin/certifications/edit?id=' . $ownerId;
require __DIR__ . '/../_assets.php';
?>
<?php endif; ?>

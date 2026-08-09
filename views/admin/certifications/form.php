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
                    <thead><tr><th>Curso</th><th>Relación</th><th>Precio bundle</th><th>Plataforma</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($linkedCourses as $c): ?>
                        <tr>
                            <td><?= e($c['course_name']) ?></td>
                            <td><?= e($relationTypes[$c['relation_type']] ?? $c['relation_type']) ?></td>
                            <td>
                                <?php if (($c['relation_type'] ?? '') === 'bundle_discount' && $c['bundle_price'] !== null): ?>
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
            <label id="bundlePriceField" style="display:none">Precio bundle
                <input type="number" step="0.01" name="bundle_price">
                <small class="muted">Solo aplica con bundle con descuento.</small>
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
  const syncRel = () => {
    if (!bundle || !rel) return;
    bundle.style.display = rel.value === 'bundle_discount' ? '' : 'none';
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

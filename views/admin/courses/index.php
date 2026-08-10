<?php
require __DIR__ . '/../_nav.php';
$items = $items ?? [];
$certifications = $certifications ?? [];
$relationTypes = $relationTypes ?? \App\Catalog\CatalogRepository::courseRelationTypes();
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Cursos</h2>
            <p class="muted" style="margin:0.35rem 0 0">Indica si cada curso ya está ligado a una certificación; si no, vincúlalo desde aquí.</p>
        </div>
        <a class="btn" href="/admin/courses/create">Nuevo</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Plataforma</th>
                    <th>Moodle ID</th>
                    <th>Certificación</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php
                $links = $item['cert_links'] ?? [];
                $linked = !empty($item['is_linked']);
                ?>
                <tr>
                    <td><code><?= e($item['code']) ?></code></td>
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($item['platform_type']) ?></td>
                    <td><?= e((string)($item['moodle_course_id'] ?? '—')) ?></td>
                    <td>
                        <?php if ($linked): ?>
                            <div class="stack" style="gap:0.45rem">
                                <?php foreach ($links as $link): ?>
                                    <div>
                                        <span class="pill pill-ok">Conectado</span>
                                        <a href="/admin/certifications/edit?id=<?= (int)$link['certification_id'] ?>">
                                            <?= e($link['certification_name']) ?>
                                        </a>
                                        <span class="muted">
                                            · <?= e($relationTypes[$link['relation_type']] ?? $link['relation_type']) ?>
                                            <?php if ($link['bundle_price'] !== null && in_array(($link['relation_type'] ?? ''), ['sold_separate', 'bundle_discount'], true)): ?>
                                                · <?= e(\App\Support\Str::money((float)$link['bundle_price'])) ?>
                                            <?php endif; ?>
                                        </span>
                                        <form method="post" action="/admin/courses/detach-certification" class="inline-form" style="display:inline"
                                              onsubmit="return confirm('¿Quitar el vínculo con esta certificación?');">
                                            <input type="hidden" name="course_id" value="<?= (int)$item['id'] ?>">
                                            <input type="hidden" name="certification_id" value="<?= (int)$link['certification_id'] ?>">
                                            <button type="submit" class="linkish">Quitar</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="pill pill-muted">Sin vincular</span>
                            <?php if ($certifications): ?>
                                <form method="post" action="/admin/courses/attach-certification" class="stack form-grid course-link-form" style="margin-top:0.55rem" data-course-link>
                                    <input type="hidden" name="course_id" value="<?= (int)$item['id'] ?>">
                                    <label>Certificación
                                        <select name="certification_id" required>
                                            <option value="">— Elegir —</option>
                                            <?php foreach ($certifications as $cert): ?>
                                                <option value="<?= (int)$cert['id'] ?>">
                                                    <?= e($cert['name']) ?> (<?= e($cert['code']) ?>) · <?= e($cert['provider_name'] ?? '') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Relación
                                        <select name="relation_type" data-relation>
                                            <?php foreach ($relationTypes as $value => $label): ?>
                                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label data-price-field style="display:none">Precio (MXN)
                                        <input type="number" step="0.01" min="0" name="bundle_price" data-price-input>
                                        <small class="muted" data-price-hint>Solo si se vende por separado.</small>
                                    </label>
                                    <div class="actions">
                                        <button class="btn" type="submit">Vincular</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <p class="muted" style="margin:0.35rem 0 0">Primero crea certificaciones en Proveedores.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><a href="/admin/courses/edit?id=<?= (int)$item['id'] ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <tr><td colspan="6" class="muted">No hay cursos registrados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
(() => {
  const syncForm = (form) => {
    const rel = form.querySelector('[data-relation]');
    const priceField = form.querySelector('[data-price-field]');
    const hint = form.querySelector('[data-price-hint]');
    const input = form.querySelector('[data-price-input]');
    if (!rel || !priceField) return;
    const type = rel.value;
    const show = type === 'sold_separate' || type === 'bundle_discount';
    priceField.style.display = show ? '' : 'none';
    if (input) {
      input.required = type === 'sold_separate';
      if (!show) input.value = '';
    }
    if (hint) {
      hint.textContent = type === 'sold_separate'
        ? 'Requerido: precio de venta del curso.'
        : 'Opcional en bundle (gratuito/incluido no pide precio).';
    }
  };
  document.querySelectorAll('[data-course-link]').forEach((form) => {
    form.querySelector('[data-relation]')?.addEventListener('change', () => syncForm(form));
    syncForm(form);
  });
})();
</script>

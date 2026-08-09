<?php
require __DIR__ . '/../_nav.php';
$providers = $providers ?? [];
$tiers = $tiers ?? [];
$items = $items ?? [];
$regulations = $regulations ?? [];
$filters = $filters ?? [];
$providerId = (int) ($filters['provider_id'] ?? 0);
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Precios y reglamentos</h2>
            <p class="muted" style="margin:0.35rem 0 0">
                Actualiza costo Doceo, precio público y precios por nivel TR de varias certificaciones a la vez.
                El reglamento suele ser el mismo por empresa: asígnalo a todas o fila por fila.
            </p>
        </div>
        <div class="actions">
            <a class="btn btn-ghost" href="/admin/certifications">Lista de fichas</a>
            <a class="btn btn-ghost" href="/admin/documents">Documentos</a>
        </div>
    </div>

    <form method="get" class="filters stack form-grid" style="margin-top:1rem">
        <label>Proveedor / empresa
            <select name="provider_id" required onchange="this.form.submit()">
                <option value="">— Elige empresa —</option>
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $providerId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Buscar
            <input name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="nombre o código">
        </label>
        <button class="btn" type="submit">Ver</button>
    </form>
</section>

<?php if ($providerId <= 0): ?>
    <section class="note">
        <p class="muted">Elige un proveedor para cargar todas sus certificaciones en la matriz.</p>
    </section>
<?php else: ?>

<section class="note">
    <h3>Reglamento de la empresa</h3>
    <p class="muted">Cuando UKS (u otra) actualiza el PDF, súbelo en Documentos y asígnalo aquí a todas las certificaciones de esta empresa.</p>
    <form method="post" action="/admin/certifications/pricing/assign-regulation" class="stack form-grid">
        <input type="hidden" name="provider_id" value="<?= $providerId ?>">
        <input type="hidden" name="q" value="<?= e($filters['q'] ?? '') ?>">
        <label>Reglamento (documentos tipo reglamento)
            <select name="document_id" required>
                <option value="">—</option>
                <?php foreach ($regulations as $doc): ?>
                    <option value="<?= (int)$doc['id'] ?>">
                        <?= e($doc['title']) ?> · v<?= e((string)$doc['version']) ?>
                        <?= empty($doc['provider_id']) ? ' (general)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="actions">
            <button class="btn" type="submit" onclick="return confirm('¿Asignar este reglamento a TODAS las certificaciones de esta empresa?');">
                Asignar a todas las de esta empresa
            </button>
            <?php if (!$regulations): ?>
                <span class="muted">No hay reglamentos activos. Crea uno en Documentos (tipo reglamento, proveedor = esta empresa).</span>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="note">
    <h3>Ajuste rápido de precios</h3>
    <p class="muted">Útil cuando sube el costo de toda la línea. Aplica sobre los valores actuales de las filas visibles y luego guarda.</p>
    <div class="stack form-grid" id="bulkAdjustBar">
        <label>Tipo
            <select id="bulkMode">
                <option value="percent">Aumentar %</option>
                <option value="amount">Sumar $</option>
            </select>
        </label>
        <label>Valor
            <input type="number" step="0.01" id="bulkValue" value="0" placeholder="ej. 10">
        </label>
        <label class="check"><input type="checkbox" id="bulkCost" checked> Costo Doceo</label>
        <label class="check"><input type="checkbox" id="bulkPublic" checked> Público</label>
        <label class="check"><input type="checkbox" id="bulkTiers" checked> Niveles TR</label>
        <div class="actions">
            <button class="btn btn-ghost" type="button" id="bulkApplyBtn">Aplicar en la tabla</button>
        </div>
    </div>
</section>

<section class="note">
    <form method="post" action="/admin/certifications/pricing/save" id="pricingMatrixForm">
        <input type="hidden" name="provider_id" value="<?= $providerId ?>">
        <input type="hidden" name="q" value="<?= e($filters['q'] ?? '') ?>">
        <div class="actions" style="margin-bottom:0.75rem">
            <button class="btn" type="submit">Guardar precios y reglamentos</button>
            <span class="muted"><?= count($items) ?> certificación(es)</span>
        </div>
        <div class="table-wrap pricing-matrix-wrap">
            <table class="data-table pricing-matrix">
                <thead>
                    <tr>
                        <th class="pricing-sticky-col">Certificación</th>
                        <th>Costo Doceo</th>
                        <th>Público</th>
                        <?php foreach ($tiers as $tier): ?>
                            <th>TR <?= e($tier['name']) ?></th>
                        <?php endforeach; ?>
                        <th>Reglamento</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    $cid = (int) $item['id'];
                    $tierPrices = $item['tier_prices'] ?? [];
                    $regId = (int) ($item['regulation_document_id'] ?? 0);
                    ?>
                    <tr>
                        <td class="pricing-sticky-col">
                            <strong><?= e($item['name']) ?></strong>
                            <div class="muted"><code><?= e($item['code']) ?></code>
                                · <a href="/admin/certifications/edit?id=<?= $cid ?>">ficha</a>
                            </div>
                        </td>
                        <td>
                            <input class="price-input" type="number" step="0.01" min="0"
                                   name="rows[<?= $cid ?>][cost_price]"
                                   value="<?= e($item['cost_price'] !== null && $item['cost_price'] !== '' ? (string)$item['cost_price'] : '') ?>"
                                   data-col="cost">
                        </td>
                        <td>
                            <input class="price-input" type="number" step="0.01" min="0"
                                   name="rows[<?= $cid ?>][public_price]"
                                   value="<?= e($item['public_price'] !== null && $item['public_price'] !== '' ? (string)$item['public_price'] : '') ?>"
                                   data-col="public">
                        </td>
                        <?php foreach ($tiers as $tier): ?>
                            <?php $tid = (int) $tier['id']; ?>
                            <td>
                                <input class="price-input" type="number" step="0.01" min="0"
                                       name="rows[<?= $cid ?>][tier_prices][<?= $tid ?>]"
                                       value="<?= isset($tierPrices[$tid]) ? e((string)$tierPrices[$tid]) : '' ?>"
                                       data-col="tier">
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <select name="rows[<?= $cid ?>][regulation_document_id]">
                                <option value="">—</option>
                                <?php foreach ($regulations as $doc): ?>
                                    <option value="<?= (int)$doc['id'] ?>" <?= $regId === (int)$doc['id'] ? 'selected' : '' ?>>
                                        <?= e($doc['title']) ?> v<?= e((string)$doc['version']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                    <tr><td colspan="<?= 4 + count($tiers) ?>" class="muted">No hay certificaciones para este proveedor.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($items): ?>
            <div class="actions" style="margin-top:1rem">
                <button class="btn" type="submit">Guardar precios y reglamentos</button>
            </div>
        <?php endif; ?>
    </form>
</section>

<script>
(function () {
  var btn = document.getElementById('bulkApplyBtn');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var mode = document.getElementById('bulkMode').value;
    var raw = parseFloat(document.getElementById('bulkValue').value);
    if (isNaN(raw)) {
      alert('Indica un valor numérico.');
      return;
    }
    var doCost = document.getElementById('bulkCost').checked;
    var doPublic = document.getElementById('bulkPublic').checked;
    var doTiers = document.getElementById('bulkTiers').checked;
    var inputs = document.querySelectorAll('#pricingMatrixForm input.price-input');
    inputs.forEach(function (el) {
      var col = el.getAttribute('data-col');
      if (col === 'cost' && !doCost) return;
      if (col === 'public' && !doPublic) return;
      if (col === 'tier' && !doTiers) return;
      var v = el.value.trim();
      if (v === '') return;
      var n = parseFloat(v);
      if (isNaN(n)) return;
      var next = mode === 'percent' ? n * (1 + raw / 100) : n + raw;
      el.value = (Math.round(next * 100) / 100).toFixed(2);
    });
  });
})();
</script>
<?php endif; ?>

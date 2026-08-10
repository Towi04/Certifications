<?php
require __DIR__ . '/../_nav.php';
$providers = $providers ?? [];
$tiers = $tiers ?? [];
$items = $items ?? [];
$filters = $filters ?? [];
$providerId = (int) ($filters['provider_id'] ?? 0);
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Precios</h2>
            <p class="muted" style="margin:0.35rem 0 0">
                Define el costo de compra y genera precios de venta (público y cada nivel TR) con márgenes distintos.
                No se permite guardar si público o TR quedan por debajo del costo.
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
    <h3>Ajuste rápido desde el costo</h3>
    <p class="muted">
        Parte del <strong>Costo Doceo</strong> de cada fila y calcula el precio público y el de cada TR
        con el margen que indiques (porcentaje o pesos). Si la celda de venta está vacía, se genera;
        si ya tiene valor, se reemplaza.
    </p>
    <div class="stack form-grid" id="bulkAdjustBar">
        <label>Tipo de margen
            <select id="bulkMode">
                <option value="percent">Aumentar %</option>
                <option value="amount">Sumar $</option>
            </select>
        </label>
        <label>Margen público
            <input type="number" step="0.01" id="bulkPublicMargin" value="20" placeholder="ej. 20">
        </label>
        <?php foreach ($tiers as $tier): ?>
            <label>Margen TR <?= e($tier['name']) ?>
                <input type="number" step="0.01" class="bulk-tier-margin"
                       data-tier-id="<?= (int)$tier['id'] ?>"
                       value="15" placeholder="ej. 15">
            </label>
        <?php endforeach; ?>
        <label class="check field-wide">
            <input type="checkbox" id="bulkOnlyEmpty" value="1">
            Solo rellenar celdas vacías (no sobrescribir precios ya capturados)
        </label>
        <div class="actions">
            <button class="btn" type="button" id="bulkApplyBtn">Aplicar en la tabla</button>
            <span class="muted" id="bulkApplyMsg"></span>
        </div>
    </div>
</section>

<section class="note">
    <form method="post" action="/admin/certifications/pricing/save" id="pricingMatrixForm">
        <input type="hidden" name="provider_id" value="<?= $providerId ?>">
        <input type="hidden" name="q" value="<?= e($filters['q'] ?? '') ?>">
        <div class="actions pricing-save-bar">
            <button class="btn" type="submit" id="pricingSaveBtn">Guardar precios</button>
            <span class="muted"><?= count($items) ?> certificación(es)</span>
            <span class="pricing-loss-banner" id="pricingLossBanner" hidden>
                Hay precios por debajo del costo (en rojo). Corrígelos antes de guardar.
            </span>
        </div>

        <div class="table-wrap pricing-matrix-wrap pricing-matrix-desktop">
            <table class="data-table pricing-matrix">
                <thead>
                    <tr>
                        <th class="pricing-sticky-col">Certificación</th>
                        <th>Costo Doceo</th>
                        <th>Público</th>
                        <?php foreach ($tiers as $tier): ?>
                            <th>TR <?= e($tier['name']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    $cid = (int) $item['id'];
                    $tierPrices = $item['tier_prices'] ?? [];
                    ?>
                    <tr class="pricing-row" data-cert-id="<?= $cid ?>">
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
                                   data-col="cost" data-cert="<?= $cid ?>" inputmode="decimal">
                        </td>
                        <td>
                            <input class="price-input" type="number" step="0.01" min="0"
                                   name="rows[<?= $cid ?>][public_price]"
                                   value="<?= e($item['public_price'] !== null && $item['public_price'] !== '' ? (string)$item['public_price'] : '') ?>"
                                   data-col="public" data-cert="<?= $cid ?>" inputmode="decimal">
                            <div class="price-hint" data-hint-for="public-<?= $cid ?>"></div>
                        </td>
                        <?php foreach ($tiers as $tier): ?>
                            <?php $tid = (int) $tier['id']; ?>
                            <td>
                                <input class="price-input" type="number" step="0.01" min="0"
                                       name="rows[<?= $cid ?>][tier_prices][<?= $tid ?>]"
                                       value="<?= isset($tierPrices[$tid]) ? e((string)$tierPrices[$tid]) : '' ?>"
                                       data-col="tier" data-tier-id="<?= $tid ?>" data-cert="<?= $cid ?>" inputmode="decimal">
                                <div class="price-hint" data-hint-for="tier-<?= $cid ?>-<?= $tid ?>"></div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                    <tr><td colspan="<?= 3 + count($tiers) ?>" class="muted">No hay certificaciones para este proveedor.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($items): ?>
            <div class="pricing-matrix-mobile" aria-label="Precios por certificación">
                <?php foreach ($items as $item): ?>
                    <?php
                    $cid = (int) $item['id'];
                    $tierPrices = $item['tier_prices'] ?? [];
                    ?>
                    <article class="pricing-card pricing-row" data-cert-id="<?= $cid ?>">
                        <header>
                            <strong><?= e($item['name']) ?></strong>
                            <p class="muted"><code><?= e($item['code']) ?></code>
                                · <a href="/admin/certifications/edit?id=<?= $cid ?>">ficha</a></p>
                        </header>
                        <label>Costo Doceo
                            <input class="price-input" type="number" step="0.01" min="0"
                                   data-sync-name="rows[<?= $cid ?>][cost_price]"
                                   value="<?= e($item['cost_price'] !== null && $item['cost_price'] !== '' ? (string)$item['cost_price'] : '') ?>"
                                   data-col="cost" data-cert="<?= $cid ?>" inputmode="decimal">
                        </label>
                        <label>Público
                            <input class="price-input" type="number" step="0.01" min="0"
                                   data-sync-name="rows[<?= $cid ?>][public_price]"
                                   value="<?= e($item['public_price'] !== null && $item['public_price'] !== '' ? (string)$item['public_price'] : '') ?>"
                                   data-col="public" data-cert="<?= $cid ?>" inputmode="decimal">
                            <div class="price-hint" data-hint-for="public-<?= $cid ?>"></div>
                        </label>
                        <?php foreach ($tiers as $tier): ?>
                            <?php $tid = (int) $tier['id']; ?>
                            <label>TR <?= e($tier['name']) ?>
                                <input class="price-input" type="number" step="0.01" min="0"
                                       data-sync-name="rows[<?= $cid ?>][tier_prices][<?= $tid ?>]"
                                       value="<?= isset($tierPrices[$tid]) ? e((string)$tierPrices[$tid]) : '' ?>"
                                       data-col="tier" data-tier-id="<?= $tid ?>" data-cert="<?= $cid ?>" inputmode="decimal">
                                <div class="price-hint" data-hint-for="tier-<?= $cid ?>-<?= $tid ?>"></div>
                            </label>
                        <?php endforeach; ?>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="actions pricing-save-bar" style="margin-top:1rem">
                <button class="btn" type="submit">Guardar precios</button>
            </div>
        <?php endif; ?>
    </form>
</section>

<script>
(function () {
  var form = document.getElementById('pricingMatrixForm');
  if (!form) return;

  function money(n) {
    return (Math.round(n * 100) / 100).toFixed(2);
  }

  function parseNum(el) {
    if (!el) return NaN;
    var v = String(el.value || '').trim();
    if (v === '') return NaN;
    return parseFloat(v);
  }

  function applyMargin(cost, margin, mode) {
    if (mode === 'percent') return cost * (1 + margin / 100);
    return cost + margin;
  }

  function syncMobileToDesktop() {
    form.querySelectorAll('.pricing-matrix-mobile [data-sync-name]').forEach(function (src) {
      var name = src.getAttribute('data-sync-name');
      var dest = form.querySelector('.pricing-matrix-desktop [name="' + name.replace(/"/g, '\\"') + '"]');
      if (dest) dest.value = src.value;
    });
  }

  function syncDesktopToMobile() {
    form.querySelectorAll('.pricing-matrix-desktop [name]').forEach(function (src) {
      var name = src.getAttribute('name');
      if (!name) return;
      var dest = form.querySelector('.pricing-matrix-mobile [data-sync-name="' + name.replace(/"/g, '\\"') + '"]');
      if (dest) dest.value = src.value;
    });
  }

  function refreshRow(certId) {
    var costEl = form.querySelector('.pricing-matrix-desktop input[data-col="cost"][data-cert="' + certId + '"]');
    var cost = parseNum(costEl);
    var sellInputs = form.querySelectorAll('input.price-input[data-cert="' + certId + '"]:not([data-col="cost"])');
    var hasLoss = false;

    sellInputs.forEach(function (el) {
      var price = parseNum(el);
      var hintKey = el.getAttribute('data-col') === 'public'
        ? 'public-' + certId
        : 'tier-' + certId + '-' + el.getAttribute('data-tier-id');
      var hints = form.querySelectorAll('[data-hint-for="' + hintKey + '"]');
      el.classList.remove('price-ok', 'price-warn', 'price-bad');

      if (isNaN(cost) || isNaN(price)) {
        hints.forEach(function (h) { h.textContent = ''; h.className = 'price-hint'; });
        return;
      }

      var profit = price - cost;
      var pct = cost > 0 ? (profit / cost) * 100 : 0;
      var cls = 'price-ok';
      var hintCls = 'price-hint is-ok';
      if (profit < 0) {
        cls = 'price-bad';
        hintCls = 'price-hint is-bad';
        hasLoss = true;
      } else if (pct < 5) {
        cls = 'price-warn';
        hintCls = 'price-hint is-warn';
      }
      el.classList.add(cls);
      hints.forEach(function (h) {
        h.className = hintCls;
        h.textContent = profit < 0
          ? 'Pérdida $' + money(Math.abs(profit))
          : 'Ganancia $' + money(profit) + ' (' + money(pct) + '%)';
      });
    });

    sellInputs.forEach(function (el) {
      var name = el.getAttribute('name') || el.getAttribute('data-sync-name');
      if (!name) return;
      var pair = el.getAttribute('name')
        ? form.querySelector('.pricing-matrix-mobile [data-sync-name="' + name.replace(/"/g, '\\"') + '"]')
        : form.querySelector('.pricing-matrix-desktop [name="' + name.replace(/"/g, '\\"') + '"]');
      if (!pair) return;
      pair.classList.remove('price-ok', 'price-warn', 'price-bad');
      if (el.classList.contains('price-bad')) pair.classList.add('price-bad');
      else if (el.classList.contains('price-warn')) pair.classList.add('price-warn');
      else if (el.classList.contains('price-ok')) pair.classList.add('price-ok');
    });

    return hasLoss;
  }

  function refreshAll() {
    var loss = false;
    form.querySelectorAll('.pricing-matrix-desktop .pricing-row[data-cert-id]').forEach(function (row) {
      if (refreshRow(row.getAttribute('data-cert-id'))) loss = true;
    });
    var banner = document.getElementById('pricingLossBanner');
    if (banner) banner.hidden = !loss;
    form.querySelectorAll('#pricingSaveBtn, .pricing-save-bar .btn[type="submit"]').forEach(function (btn) {
      btn.disabled = loss;
      btn.title = loss ? 'Corrige precios en rojo antes de guardar' : '';
    });
    return loss;
  }

  form.addEventListener('input', function (e) {
    var t = e.target;
    if (!t.classList.contains('price-input') && t.tagName !== 'SELECT') return;
    if (t.hasAttribute('data-sync-name')) {
      var dest = form.querySelector('.pricing-matrix-desktop [name="' + t.getAttribute('data-sync-name').replace(/"/g, '\\"') + '"]');
      if (dest) dest.value = t.value;
    } else if (t.getAttribute('name')) {
      var mob = form.querySelector('.pricing-matrix-mobile [data-sync-name="' + t.getAttribute('name').replace(/"/g, '\\"') + '"]');
      if (mob) mob.value = t.value;
    }
    var cert = t.getAttribute('data-cert');
    if (cert) refreshRow(cert);
    refreshAll();
  });

  form.addEventListener('submit', function (e) {
    syncMobileToDesktop();
    if (refreshAll()) {
      e.preventDefault();
      alert('Hay precios por debajo del costo (marcados en rojo). Corrígelos antes de guardar.');
    }
  });

  var btn = document.getElementById('bulkApplyBtn');
  if (btn) {
    btn.addEventListener('click', function () {
      var mode = document.getElementById('bulkMode').value;
      var publicMargin = parseFloat(document.getElementById('bulkPublicMargin').value);
      if (isNaN(publicMargin)) {
        alert('Indica el margen público.');
        return;
      }
      var onlyEmpty = document.getElementById('bulkOnlyEmpty').checked;
      var tierMargins = {};
      document.querySelectorAll('.bulk-tier-margin').forEach(function (el) {
        var tid = el.getAttribute('data-tier-id');
        var m = parseFloat(el.value);
        if (!isNaN(m)) tierMargins[tid] = m;
      });

      var updated = 0;
      var skipped = 0;
      form.querySelectorAll('.pricing-matrix-desktop .pricing-row[data-cert-id]').forEach(function (row) {
        var certId = row.getAttribute('data-cert-id');
        var costEl = row.querySelector('input[data-col="cost"]');
        var cost = parseNum(costEl);
        if (isNaN(cost) || cost < 0) {
          skipped += 1;
          return;
        }

        var publicEl = row.querySelector('input[data-col="public"]');
        if (publicEl && (!onlyEmpty || publicEl.value.trim() === '')) {
          publicEl.value = money(applyMargin(cost, publicMargin, mode));
          updated += 1;
        }

        row.querySelectorAll('input[data-col="tier"]').forEach(function (el) {
          var tid = el.getAttribute('data-tier-id');
          if (!(tid in tierMargins)) return;
          if (onlyEmpty && el.value.trim() !== '') return;
          el.value = money(applyMargin(cost, tierMargins[tid], mode));
          updated += 1;
        });
      });

      syncDesktopToMobile();
      refreshAll();
      var msg = document.getElementById('bulkApplyMsg');
      if (msg) {
        msg.textContent = updated
          ? ('Actualizadas ' + updated + ' celda(s)' + (skipped ? '; ' + skipped + ' sin costo' : '') + '. Revisa y guarda.')
          : (skipped ? 'Ninguna fila tenía costo Doceo.' : 'No se modificó ninguna celda.');
      }
    });
  }

  refreshAll();
})();
</script>
<?php endif; ?>

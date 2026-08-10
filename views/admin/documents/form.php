<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$docTypes = $docTypes ?? [];
$providers = $providers ?? [];
$groups = $groups ?? [];
$certifications = $certifications ?? [];
$groupsByProvider = $groupsByProvider ?? [];
$certsByProvider = $certsByProvider ?? [];
$scopeType = $item['scope_type'] ?? 'provider';
$docAccept = '.pdf,.csv,.xlsx,.xls,.doc,.docx,application/pdf,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document';
$providerId = (int) ($item['provider_id'] ?? 0);
?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <p class="muted">Sube reglamentos u otros archivos. Al actualizar, cambia la versión (ej. 1.0 → 1.1). Cada documento activo tiene enlace público de descarga.</p>
    <form method="post" action="/admin/documents/save" enctype="multipart/form-data" class="stack form-grid" id="globalDocForm">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>

        <label>Nombre
            <input name="title" required value="<?= e($item['title'] ?? '') ?>" placeholder="Reglamento ELET">
        </label>
        <label>Versión
            <input name="version" required value="<?= e($item['version'] ?? '1.0') ?>" placeholder="1.0">
        </label>
        <label>Proveedor
            <select name="provider_id" id="docProviderId" required>
                <option value="">— Selecciona —</option>
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $providerId === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tipo
            <select name="doc_type">
                <?php foreach ($docTypes as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($item['doc_type'] ?? 'regulation') === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Alcance
            <select name="scope_type" id="docScopeType">
                <option value="provider" <?= $scopeType === 'provider' ? 'selected' : '' ?>>Empresa (proveedor)</option>
                <option value="group" <?= $scopeType === 'group' ? 'selected' : '' ?>>Grupo</option>
                <option value="certification" <?= $scopeType === 'certification' ? 'selected' : '' ?>>Certificación</option>
            </select>
        </label>
        <label id="docScopeGroupField" style="<?= $scopeType === 'group' ? '' : 'display:none' ?>">Grupo
            <select name="provider_group_id" id="docGroupSelect">
                <option value="">—</option>
                <?php foreach ($groups as $g): ?>
                    <option value="<?= (int)$g['id'] ?>" <?= (int)($item['provider_group_id'] ?? 0) === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label id="docScopeCertField" style="<?= $scopeType === 'certification' ? '' : 'display:none' ?>">Certificación
            <select name="certification_id" id="docCertSelect">
                <option value="">—</option>
                <?php foreach ($certifications as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)($item['certification_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Código interno (opcional)
            <input name="code" value="<?= e($item['code'] ?? '') ?>" placeholder="Se genera solo si lo dejas vacío">
        </label>
        <label>Archivo <?= $item ? '(dejar vacío para conservar el actual)' : '' ?>
            <input type="file" name="file" accept="<?= e($docAccept) ?>" <?= $item ? '' : 'required' ?>>
        </label>
        <?php if (!empty($item['file_path'])): ?>
            <p class="muted" style="grid-column:1/-1">
                Actual:
                <a href="/media?f=<?= e(rawurlencode($item['file_path'])) ?>" target="_blank" rel="noopener">ver archivo</a>
            </p>
        <?php endif; ?>
        <label style="grid-column:1/-1">Notas internas (opcional)
            <textarea name="notes" rows="3"><?= e($item['body_html'] ?? '') ?></textarea>
        </label>
        <label class="check">
            <input type="checkbox" name="is_active" <?= !isset($item) || (int)($item['is_active'] ?? 1) ? 'checked' : '' ?>>
            Activo
        </label>
        <div class="actions" style="grid-column:1/-1">
            <button class="btn" type="submit">Guardar</button>
            <a class="btn btn-ghost" href="/admin/documents">Volver</a>
        </div>
    </form>
</section>
<script>
(function () {
  const groupsByProvider = <?= json_encode($groupsByProvider, JSON_UNESCAPED_UNICODE) ?>;
  const certsByProvider = <?= json_encode($certsByProvider, JSON_UNESCAPED_UNICODE) ?>;
  const providerSel = document.getElementById('docProviderId');
  const scopeSel = document.getElementById('docScopeType');
  const groupField = document.getElementById('docScopeGroupField');
  const certField = document.getElementById('docScopeCertField');
  const groupSel = document.getElementById('docGroupSelect');
  const certSel = document.getElementById('docCertSelect');

  function fillSelect(sel, items, labelKey) {
    if (!sel) return;
    const current = sel.value;
    sel.innerHTML = '<option value="">—</option>';
    (items || []).forEach(function (row) {
      const opt = document.createElement('option');
      opt.value = String(row.id);
      opt.textContent = row[labelKey] || row.name || row.id;
      sel.appendChild(opt);
    });
    if (current) sel.value = current;
  }

  function syncScope() {
    const scope = scopeSel?.value || 'provider';
    if (groupField) groupField.style.display = scope === 'group' ? '' : 'none';
    if (certField) certField.style.display = scope === 'certification' ? '' : 'none';
  }

  function syncProvider() {
    const pid = parseInt(providerSel?.value || '0', 10);
    fillSelect(groupSel, groupsByProvider[pid] || groupsByProvider[String(pid)] || [], 'name');
    fillSelect(certSel, certsByProvider[pid] || certsByProvider[String(pid)] || [], 'name');
  }

  providerSel?.addEventListener('change', syncProvider);
  scopeSel?.addEventListener('change', syncScope);
  syncScope();
  if (!<?= $item ? 'true' : 'false' ?>) syncProvider();
})();
</script>

<?php
require __DIR__ . '/../_nav.php';
$docTypes = $docTypes ?? [];
$providers = $providers ?? [];
$providerFilter = $providerFilter ?? null;
$appUrl = rtrim((string) ($appUrl ?? ''), '/');

$iconView = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6.5 9.5-6.5S21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/></svg>';
$iconDownload = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4v10m0 0 4-4m-4 4-4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 18h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconCopy = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M4 16V6a2 2 0 0 1 2-2h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconDelete = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h14M10 7V5h4v2m-6 3v7m4-7v7M7 7l1 12h8l1-12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$iconEdit = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M13.5 6.5l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';

$scopeLabel = static function (array $item): string {
    $scope = (string) ($item['scope_type'] ?? 'provider');
    if ($scope === 'group') {
        return 'Grupo: ' . ($item['group_name'] ?? '—');
    }
    if ($scope === 'certification') {
        return 'Cert: ' . ($item['certification_name'] ?? '—');
    }
    return 'Empresa';
};
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Documentos</h2>
            <p class="muted" style="margin:0.35rem 0 0">
                Reglamentos, formularios e instrucciones. Cada archivo tiene versión, alcance y enlace público.
            </p>
        </div>
        <a class="btn" href="/admin/documents/create">Subir documento</a>
    </div>

    <form method="get" action="/admin/documents" class="stack form-grid" style="margin-top:1rem">
        <label>Filtrar por proveedor
            <select name="provider_id" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)$providerFilter === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Versión</th>
                <th>Proveedor</th>
                <th>Tipo</th>
                <th>Alcance</th>
                <th>Enlace</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$items): ?>
                <tr><td colspan="7" class="muted">Aún no hay documentos. Sube el primero.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
                <?php
                $hasFile = !empty($item['file_path']);
                $viewUrl = $hasFile ? '/media?f=' . rawurlencode((string) $item['file_path']) : '';
                $downloadUrl = $hasFile
                    ? '/media?f=' . rawurlencode((string) $item['file_path'])
                        . '&download=1&name=' . rawurlencode((string) $item['title'] . '_v' . $item['version'])
                    : '';
                $shareToken = trim((string) ($item['share_token'] ?? ''));
                $shareUrl = ($appUrl !== '' && $shareToken !== '') ? $appUrl . '/d/' . rawurlencode($shareToken) : '';
                ?>
                <tr>
                    <td>
                        <strong><?= e($item['title']) ?></strong>
                        <?php if (!(int)($item['is_active'] ?? 1)): ?>
                            <span class="pill">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= e($item['version']) ?></code></td>
                    <td><?= e($item['provider_name'] ?? '—') ?></td>
                    <td><?= e($docTypes[$item['doc_type']] ?? $item['doc_type']) ?></td>
                    <td><?= e($scopeLabel($item)) ?></td>
                    <td>
                        <?php if ($shareUrl): ?>
                            <button type="button" class="icon-btn js-copy-doc-link" data-url="<?= e($shareUrl) ?>" title="Copiar enlace público" aria-label="Copiar enlace"><?= $iconCopy ?></button>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="icon-actions">
                            <a class="icon-btn" href="/admin/documents/edit?id=<?= (int)$item['id'] ?>" title="Editar" aria-label="Editar"><?= $iconEdit ?></a>
                            <?php if ($hasFile): ?>
                                <a class="icon-btn" href="<?= e($viewUrl) ?>" target="_blank" rel="noopener" title="Ver" aria-label="Ver"><?= $iconView ?></a>
                                <a class="icon-btn" href="<?= e($downloadUrl) ?>" title="Descargar" aria-label="Descargar"><?= $iconDownload ?></a>
                            <?php else: ?>
                                <span class="icon-btn is-disabled" title="Sin archivo" aria-hidden="true"><?= $iconView ?></span>
                            <?php endif; ?>
                            <form method="post" action="/admin/documents/delete" class="inline-form"
                                  onsubmit="return confirm(<?= json_encode('¿Eliminar “' . $item['title'] . '” v' . $item['version'] . '?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <button type="submit" class="icon-btn icon-btn-danger" title="Eliminar" aria-label="Eliminar"><?= $iconDelete ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
(function () {
  document.querySelectorAll('.js-copy-doc-link').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      var url = btn.getAttribute('data-url') || '';
      if (!url) return;
      try {
        await navigator.clipboard.writeText(url);
        btn.setAttribute('title', 'Enlace copiado');
        setTimeout(function () { btn.setAttribute('title', 'Copiar enlace público'); }, 1800);
      } catch (e) {
        window.prompt('Copia este enlace:', url);
      }
    });
  });
})();
</script>

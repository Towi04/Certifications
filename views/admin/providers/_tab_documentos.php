<?php
$docFormOpen = $showForm || $editDocument;
$scopeType = $editDocument['scope_type'] ?? 'provider';
$docAccept = '.pdf,.csv,.xlsx,.xls,.doc,.docx,application/pdf,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document';

$scopeLabel = static function (array $doc) use ($groups, $certifications): string {
    $scope = (string) ($doc['scope_type'] ?? 'provider');
    if ($scope === 'group') {
        return 'Grupo: ' . ($doc['group_name'] ?? '—');
    }
    if ($scope === 'certification') {
        return 'Certificación: ' . ($doc['certification_name'] ?? '—');
    }
    return 'Empresa (proveedor)';
};
?>
<section class="provider-panel">
    <div class="panel-toolbar">
        <div>
            <h3>Documentos</h3>
            <p class="muted" style="margin:0.25rem 0 0">Reglamentos, formularios e instrucciones con enlace público de descarga.</p>
        </div>
        <?php if (!$docFormOpen): ?>
            <a class="btn" href="/admin/providers/edit?id=<?= $id ?>&tab=documentos&form=1">Subir documento</a>
        <?php endif; ?>
    </div>

    <?php if ($docFormOpen): ?>
        <div class="inline-form-panel">
            <div class="panel-toolbar">
                <h4 style="margin:0"><?= $editDocument ? 'Editar documento' : 'Nuevo documento' ?></h4>
                <a class="btn btn-ghost" href="/admin/providers/edit?id=<?= $id ?>&tab=documentos">Cancelar</a>
            </div>
            <form method="post" action="/admin/providers/document/save" enctype="multipart/form-data" class="form-grid" style="margin-top:0.75rem" id="providerDocForm">
                <input type="hidden" name="provider_id" value="<?= $id ?>">
                <?php if ($editDocument): ?><input type="hidden" name="id" value="<?= (int)$editDocument['id'] ?>"><?php endif; ?>
                <label>Nombre<input name="title" required value="<?= e($editDocument['title'] ?? '') ?>"></label>
                <label>Versión<input name="version" required value="<?= e($editDocument['version'] ?? '1.0') ?>"></label>
                <label>Tipo
                    <select name="doc_type">
                        <?php foreach ($docTypes as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= ($editDocument['doc_type'] ?? 'other') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Alcance
                    <select name="scope_type" id="docScopeType">
                        <option value="provider" <?= $scopeType === 'provider' ? 'selected' : '' ?>>Empresa (todas las certificaciones)</option>
                        <option value="group" <?= $scopeType === 'group' ? 'selected' : '' ?>>Grupo</option>
                        <option value="certification" <?= $scopeType === 'certification' ? 'selected' : '' ?>>Certificación</option>
                    </select>
                </label>
                <label id="docScopeGroupField" style="<?= $scopeType === 'group' ? '' : 'display:none' ?>">Grupo
                    <select name="provider_group_id">
                        <option value="">—</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= (int)$g['id'] ?>" <?= (int)($editDocument['provider_group_id'] ?? 0) === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label id="docScopeCertField" style="<?= $scopeType === 'certification' ? '' : 'display:none' ?>">Certificación
                    <select name="certification_id">
                        <option value="">—</option>
                        <?php foreach ($certifications as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)($editDocument['certification_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Archivo <?= $editDocument ? '(vacío = conservar)' : '' ?>
                    <input type="file" name="file" accept="<?= e($docAccept) ?>" <?= $editDocument ? '' : 'required' ?>>
                </label>
                <label class="field-wide">Notas internas
                    <textarea name="notes" rows="2"><?= e($editDocument['body_html'] ?? '') ?></textarea>
                </label>
                <label class="check"><input type="checkbox" name="is_active" <?= !isset($editDocument) || (int)($editDocument['is_active'] ?? 1) ? 'checked' : '' ?>> Activo</label>
                <div class="actions"><button class="btn" type="submit">Guardar documento</button></div>
            </form>
        </div>
        <script>
        (() => {
          const sel = document.getElementById('docScopeType');
          const group = document.getElementById('docScopeGroupField');
          const cert = document.getElementById('docScopeCertField');
          const sync = () => {
            const v = sel?.value || 'provider';
            if (group) group.style.display = v === 'group' ? '' : 'none';
            if (cert) cert.style.display = v === 'certification' ? '' : 'none';
          };
          sel?.addEventListener('change', sync);
          sync();
        })();
        </script>
    <?php endif; ?>

    <?php if ($provider_documents): ?>
        <div class="table-wrap" style="margin-top:1rem">
            <table class="data-table">
                <thead><tr><th>Nombre</th><th>Tipo</th><th>Alcance</th><th>Enlace</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($provider_documents as $doc): ?>
                    <?php
                    $shareToken = trim((string) ($doc['share_token'] ?? ''));
                    $shareUrl = ($appUrl !== '' && $shareToken !== '')
                        ? $appUrl . '/d/' . rawurlencode($shareToken)
                        : '';
                    ?>
                    <tr class="<?= (int)($doc['is_active'] ?? 1) ? '' : 'is-row-inactive' ?>">
                        <td>
                            <strong><?= e($doc['title']) ?></strong>
                            <br><code class="muted">v<?= e($doc['version']) ?></code>
                        </td>
                        <td><?= e($docTypes[$doc['doc_type']] ?? $doc['doc_type']) ?></td>
                        <td><?= e($scopeLabel($doc)) ?></td>
                        <td>
                            <?php if ($shareUrl): ?>
                                <div class="icon-actions">
                                    <input type="text" class="share-url-input" readonly value="<?= e($shareUrl) ?>" style="max-width:14rem;font-size:0.85rem">
                                    <button type="button" class="icon-btn js-copy-link" data-url="<?= e($shareUrl) ?>" title="Copiar enlace" aria-label="Copiar enlace"><?= $iconCopy ?></button>
                                </div>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="icon-actions">
                                <a class="icon-btn" href="/admin/providers/edit?id=<?= $id ?>&tab=documentos&edit_doc=<?= (int)$doc['id'] ?>" title="Editar" aria-label="Editar"><?= $iconEdit ?></a>
                                <?php if (!empty($doc['file_path'])): ?>
                                    <a class="icon-btn" href="/media?f=<?= e(rawurlencode((string)$doc['file_path'])) ?>" target="_blank" rel="noopener" title="Ver" aria-label="Ver"><?= $iconEye ?></a>
                                <?php endif; ?>
                                <form method="post" action="/admin/providers/document/delete" class="inline-form"
                                      onsubmit="return confirm(<?= json_encode('¿Eliminar “' . $doc['title'] . '”?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);">
                                    <input type="hidden" name="provider_id" value="<?= $id ?>">
                                    <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
                                    <button type="submit" class="icon-btn icon-btn-danger" title="Eliminar" aria-label="Eliminar"><?= $iconTrash ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        document.querySelectorAll('.js-copy-link').forEach((btn) => {
          btn.addEventListener('click', async () => {
            const url = btn.getAttribute('data-url') || '';
            if (!url) return;
            try {
              await navigator.clipboard.writeText(url);
              btn.setAttribute('title', 'Copiado');
              setTimeout(() => btn.setAttribute('title', 'Copiar enlace'), 1500);
            } catch (e) {
              window.prompt('Copia este enlace:', url);
            }
          });
        });
        </script>
    <?php elseif (!$docFormOpen): ?>
        <p class="muted">Sin documentos para este proveedor.</p>
    <?php endif; ?>
</section>

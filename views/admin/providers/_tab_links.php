<?php
$linkFormOpen = $showForm || $editLink;
$scopeType = $editLink['scope_type'] ?? 'provider';
$linkTypes = $linkTypes ?? \App\Catalog\CatalogRepository::providerLinkTypes();

$scopeLabel = static function (array $link) use ($groups, $certifications): string {
    $scope = (string) ($link['scope_type'] ?? 'provider');
    if ($scope === 'group') {
        return 'Grupo: ' . ($link['group_name'] ?? '—');
    }
    if ($scope === 'certification') {
        return 'Certificación: ' . ($link['certification_name'] ?? '—');
    }
    return 'Empresa (proveedor)';
};
?>
<section class="provider-panel">
    <div class="panel-toolbar">
        <div>
            <h3>Links para alumnos</h3>
            <p class="muted" style="margin:0.25rem 0 0">
                URLs fijas del proveedor (material de estudio, software de examen, portales…).
                Distinto de <strong>Cuentas</strong> (accesos internos de Doceo).
                Usa el código en plantillas: <code>{{Link CODIGO}}</code>, <code>{{Link CODIGO URL}}</code>, <code>{{Link CODIGO Boton}}</code>.
            </p>
        </div>
        <?php if (!$linkFormOpen): ?>
            <a class="btn" href="/admin/providers/edit?id=<?= $id ?>&tab=links&form=1">Agregar link</a>
        <?php endif; ?>
    </div>

    <?php if ($linkFormOpen): ?>
        <div class="inline-form-panel">
            <div class="panel-toolbar">
                <h4 style="margin:0"><?= $editLink ? 'Editar link' : 'Nuevo link' ?></h4>
                <a class="btn btn-ghost" href="/admin/providers/edit?id=<?= $id ?>&tab=links">Cancelar</a>
            </div>
            <form method="post" action="/admin/providers/link/save" class="form-grid" style="margin-top:0.75rem" id="providerLinkForm">
                <input type="hidden" name="provider_id" value="<?= $id ?>">
                <?php if ($editLink): ?><input type="hidden" name="id" value="<?= (int)$editLink['id'] ?>"><?php endif; ?>
                <label>Etiqueta
                    <input name="label" required value="<?= e($editLink['label'] ?? '') ?>" placeholder="Ej. Material de estudio iTEP">
                </label>
                <label>Código (token mail)
                    <input name="code" value="<?= e($editLink['code'] ?? '') ?>" placeholder="MATERIAL_ESTUDIO" pattern="[A-Za-z0-9_]*" title="Solo letras, números y guión bajo">
                    <small class="muted">Estable. Vacío = se genera desde la etiqueta. → <code>{{Link CODIGO}}</code></small>
                </label>
                <label>URL
                    <input type="url" name="url" required value="<?= e($editLink['url'] ?? '') ?>" placeholder="https://…">
                </label>
                <label>Tipo
                    <select name="link_type">
                        <?php foreach ($linkTypes as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= ($editLink['link_type'] ?? 'other') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Alcance
                    <select name="scope_type" id="linkScopeType">
                        <option value="provider" <?= $scopeType === 'provider' ? 'selected' : '' ?>>Empresa (todas las certificaciones)</option>
                        <option value="group" <?= $scopeType === 'group' ? 'selected' : '' ?>>Grupo</option>
                        <option value="certification" <?= $scopeType === 'certification' ? 'selected' : '' ?>>Certificación</option>
                    </select>
                </label>
                <label id="linkScopeGroupField" style="<?= $scopeType === 'group' ? '' : 'display:none' ?>">Grupo
                    <select name="provider_group_id">
                        <option value="">—</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= (int)$g['id'] ?>" <?= (int)($editLink['provider_group_id'] ?? 0) === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label id="linkScopeCertField" style="<?= $scopeType === 'certification' ? '' : 'display:none' ?>">Certificación
                    <select name="certification_id">
                        <option value="">—</option>
                        <?php foreach ($certifications as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)($editLink['certification_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Orden
                    <input type="number" name="sort_order" value="<?= (int)($editLink['sort_order'] ?? 0) ?>">
                </label>
                <label class="field-wide">Notas internas
                    <textarea name="notes" rows="2"><?= e($editLink['notes'] ?? '') ?></textarea>
                </label>
                <label class="check"><input type="checkbox" name="is_active" <?= !isset($editLink) || (int)($editLink['is_active'] ?? 1) ? 'checked' : '' ?>> Activo</label>
                <div class="actions"><button class="btn" type="submit">Guardar link</button></div>
            </form>
        </div>
        <script>
        (() => {
          const sel = document.getElementById('linkScopeType');
          const group = document.getElementById('linkScopeGroupField');
          const cert = document.getElementById('linkScopeCertField');
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

    <?php if ($provider_links): ?>
        <div class="table-wrap" style="margin-top:1rem">
            <table class="data-table">
                <thead><tr><th>Link</th><th>Tipo</th><th>Alcance</th><th>Token mail</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($provider_links as $link): ?>
                    <?php
                    $code = strtoupper(trim((string) ($link['code'] ?? '')));
                    $tokenHint = $code !== '' ? '{{Link ' . $code . '}}' : '';
                    $url = trim((string) ($link['url'] ?? ''));
                    ?>
                    <tr class="<?= (int)($link['is_active'] ?? 1) ? '' : 'is-row-inactive' ?>">
                        <td>
                            <strong><?= e($link['label']) ?></strong>
                            <?php if ($url !== ''): ?>
                                <br><a href="<?= e($url) ?>" target="_blank" rel="noopener" class="muted" style="font-size:0.85rem;word-break:break-all"><?= e($url) ?></a>
                            <?php endif; ?>
                        </td>
                        <td><?= e($linkTypes[$link['link_type'] ?? ''] ?? ($link['link_type'] ?? '—')) ?></td>
                        <td><?= e($scopeLabel($link)) ?></td>
                        <td>
                            <?php if ($tokenHint !== ''): ?>
                                <div class="icon-actions">
                                    <code class="share-url-input" style="font-size:0.8rem"><?= e($tokenHint) ?></code>
                                    <button type="button" class="icon-btn js-copy-link" data-url="<?= e($tokenHint) ?>" title="Copiar token" aria-label="Copiar token"><?= $iconCopy ?></button>
                                </div>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="icon-actions">
                                <a class="icon-btn" href="/admin/providers/edit?id=<?= $id ?>&tab=links&edit_link=<?= (int)$link['id'] ?>" title="Editar" aria-label="Editar"><?= $iconEdit ?></a>
                                <?php if ($url !== ''): ?>
                                    <a class="icon-btn" href="<?= e($url) ?>" target="_blank" rel="noopener" title="Abrir" aria-label="Abrir"><?= $iconEye ?></a>
                                <?php endif; ?>
                                <form method="post" action="/admin/providers/link/delete" class="inline-form"
                                      onsubmit="return confirm(<?= json_encode('¿Eliminar “' . $link['label'] . '”?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);">
                                    <input type="hidden" name="provider_id" value="<?= $id ?>">
                                    <input type="hidden" name="link_id" value="<?= (int)$link['id'] ?>">
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
              setTimeout(() => btn.setAttribute('title', 'Copiar token'), 1500);
            } catch (e) {
              window.prompt('Copia este token:', url);
            }
          });
        });
        </script>
    <?php elseif (!$linkFormOpen): ?>
        <p class="muted">Sin links para este proveedor. Agrégalos para usarlos en plantillas de correo.</p>
    <?php endif; ?>
</section>

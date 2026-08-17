<?php
$flex = \App\Catalog\FlexibleFieldService::class;
$regCatalog = \App\Catalog\CatalogRepository::registrationFieldCatalog();
$enabledBuiltin = [];
$customFields = [];
foreach ($provider_reg_fields as $row) {
    if (($row['source'] ?? '') === 'builtin') {
        $enabledBuiltin[$row['key']] = true;
    } else {
        $customFields[] = $row;
    }
}
$fieldTypes = $flex::studentFieldTypes();
$accessTypes = $flex::accessFieldTypes();
$accessMaps = $flex::accessBuiltinMaps();
$accessFields = is_array($provider_access_fields ?? null) ? $provider_access_fields : $flex::defaultAccessFields();
$groups = is_array($groups ?? null) ? $groups : [];
?>
<section class="provider-panel">
    <h3>Campos del alumno (adquisición y caso)</h3>
    <p class="muted">
        Define <strong>una sola vez</strong> los campos que este proveedor puede pedir.
        Luego, en cada certificación (o con el botón de abajo) eliges cuáles son opcionales u obligatorios.
        Incluye archivos (INE, reglamento, etc.): el alumno los sube y el correo puede usar
        <code>{{Adjunto CLAVE URL}}</code> / <code>{{Adjunto CLAVE Boton}}</code>.
    </p>

    <form method="post" action="/admin/providers/fields/save" class="form-grid" style="margin-top:1rem" enctype="multipart/form-data">
        <input type="hidden" name="provider_id" value="<?= $id ?>">

        <fieldset class="field-wide">
            <legend>Campos del catálogo</legend>
            <p class="muted">Marca los campos built-in que este proveedor puede usar en sus certificaciones.</p>
            <div class="reg-fields-grid">
                <?php foreach ($regCatalog as $key => $meta): ?>
                    <?php if (!empty($meta['locked'])): ?>
                        <label class="check is-disabled">
                            <input type="checkbox" checked disabled>
                            <?= e($meta['label']) ?> <em class="muted">(siempre requerido)</em>
                        </label>
                    <?php else: ?>
                        <label class="check">
                            <input type="checkbox" name="builtin_fields[]" value="<?= e($key) ?>"
                                <?= !empty($enabledBuiltin[$key]) ? 'checked' : '' ?>>
                            <?= e($meta['label']) ?>
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="field-wide">
            <legend>Campos personalizados (texto, archivo, URL…)</legend>
            <p class="muted">Se activan por certificación. Tipo <strong>Archivo</strong> = el alumno sube un PDF/imagen con ese nombre.</p>
            <div id="providerCustomFieldsList" class="custom-fields-list">
                <?php foreach ($customFields as $i => $cf): ?>
                    <div class="custom-field-row" data-custom-row>
                        <input type="hidden" name="custom_fields[<?= (int)$i ?>][key]" value="<?= e($cf['key']) ?>">
                        <label>Etiqueta
                            <input name="custom_fields[<?= (int)$i ?>][label]" required value="<?= e($cf['label']) ?>">
                        </label>
                        <label>Tipo
                            <select name="custom_fields[<?= (int)$i ?>][type]">
                                <?php foreach ($fieldTypes as $tv => $tl): ?>
                                    <option value="<?= e($tv) ?>" <?= ($cf['type'] ?? '') === $tv ? 'selected' : '' ?>><?= e($tl) ?></option>
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
                <button class="btn btn-ghost" type="button" id="addProviderCustomFieldBtn">Agregar campo</button>
            </div>
            <template id="providerCustomFieldTemplate">
                <div class="custom-field-row" data-custom-row>
                    <input type="hidden" name="custom_fields[__i__][key]" value="">
                    <label>Etiqueta
                        <input name="custom_fields[__i__][label]" required placeholder="Ej. INE (ambos lados) / Dirección">
                    </label>
                    <label>Tipo
                        <select name="custom_fields[__i__][type]">
                            <?php foreach ($fieldTypes as $tv => $tl): ?>
                                <option value="<?= e($tv) ?>" <?= $tv === 'file' ? 'selected' : '' ?>><?= e($tl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="check">
                        <input type="checkbox" name="custom_fields[__i__][delete]" value="1"> Eliminar
                    </label>
                </div>
            </template>
        </fieldset>

        <fieldset class="field-wide">
            <legend>Datos de acceso (los llena el admin)</legend>
            <p class="muted">
                Folios, claves, links o archivos que cargas en el caso y luego envías al alumno.
                Puedes mapear a columnas conocidas (Folio, Zoom…) o crear slots libres.
            </p>
            <div id="providerAccessFieldsList" class="custom-fields-list">
                <?php foreach ($accessFields as $i => $af): ?>
                    <div class="custom-field-row" data-access-row>
                        <input type="hidden" name="access_fields[<?= (int)$i ?>][key]" value="<?= e($af['key']) ?>">
                        <label>Etiqueta
                            <input name="access_fields[<?= (int)$i ?>][label]" required value="<?= e($af['label']) ?>">
                        </label>
                        <label>Tipo
                            <select name="access_fields[<?= (int)$i ?>][type]">
                                <?php foreach ($accessTypes as $tv => $tl): ?>
                                    <option value="<?= e($tv) ?>" <?= ($af['type'] ?? '') === $tv ? 'selected' : '' ?>><?= e($tl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Mapear a
                            <select name="access_fields[<?= (int)$i ?>][maps_to]">
                                <option value="">— valor libre —</option>
                                <?php foreach ($accessMaps as $mk => $ml): ?>
                                    <option value="<?= e($mk) ?>" <?= ($af['maps_to'] ?? '') === $mk ? 'selected' : '' ?>><?= e($ml) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="check">
                            <input type="checkbox" name="access_fields[<?= (int)$i ?>][required]" value="1"
                                <?= !empty($af['required']) ? 'checked' : '' ?>> Requerido
                        </label>
                        <label class="check">
                            <input type="checkbox" name="access_fields[<?= (int)$i ?>][delete]" value="1"> Eliminar
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="actions" style="margin-top:0.75rem">
                <button class="btn btn-ghost" type="button" id="addProviderAccessFieldBtn">Agregar dato de acceso</button>
            </div>
            <template id="providerAccessFieldTemplate">
                <div class="custom-field-row" data-access-row>
                    <input type="hidden" name="access_fields[__i__][key]" value="">
                    <label>Etiqueta
                        <input name="access_fields[__i__][label]" required placeholder="Ej. Link de plataforma">
                    </label>
                    <label>Tipo
                        <select name="access_fields[__i__][type]">
                            <?php foreach ($accessTypes as $tv => $tl): ?>
                                <option value="<?= e($tv) ?>"><?= e($tl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Mapear a
                        <select name="access_fields[__i__][maps_to]">
                            <option value="">— valor libre —</option>
                            <?php foreach ($accessMaps as $mk => $ml): ?>
                                <option value="<?= e($mk) ?>"><?= e($ml) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="check">
                        <input type="checkbox" name="access_fields[__i__][required]" value="1"> Requerido
                    </label>
                    <label class="check">
                        <input type="checkbox" name="access_fields[__i__][delete]" value="1"> Eliminar
                    </label>
                </div>
            </template>
        </fieldset>

        <div class="actions field-wide">
            <button class="btn" type="submit">Guardar campos</button>
        </div>
    </form>

    <hr style="margin:1.5rem 0">

    <h3>Aplicar a certificaciones</h3>
    <p class="muted">
        Copia el catálogo actual (alumno + accesos) a varias certificaciones de una vez,
        para no reconfigurar ficha por ficha. Por defecto deja los campos en <strong>obligatorio</strong>.
    </p>
    <form method="post" action="/admin/providers/fields/apply" class="form-grid">
        <input type="hidden" name="provider_id" value="<?= $id ?>">
        <label>Alcance
            <select name="scope">
                <option value="all">Todas las certificaciones del proveedor</option>
                <?php foreach ($groups as $g): ?>
                    <option value="group:<?= (int)$g['id'] ?>">Grupo: <?= e($g['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Modo al aplicar
            <select name="default_mode">
                <option value="required">Obligatorio</option>
                <option value="optional">Opcional</option>
            </select>
        </label>
        <div class="actions field-wide">
            <button class="btn" type="submit"
                    onclick="return confirm('¿Aplicar estos campos a las certificaciones seleccionadas? Sobrescribe la config de campos de cada una.');">
                Aplicar campos ahora
            </button>
        </div>
    </form>

    <script>
    (() => {
      function wire(listId, btnId, tplId, attr) {
        const list = document.getElementById(listId);
        const btn = document.getElementById(btnId);
        const tpl = document.getElementById(tplId);
        if (!list || !btn || !tpl) return;
        let idx = list.querySelectorAll('[' + attr + ']').length;
        btn.addEventListener('click', () => {
          const html = tpl.innerHTML.replaceAll('__i__', String(idx++));
          const wrap = document.createElement('div');
          wrap.innerHTML = html.trim();
          list.appendChild(wrap.firstElementChild);
        });
      }
      wire('providerCustomFieldsList', 'addProviderCustomFieldBtn', 'providerCustomFieldTemplate', 'data-custom-row');
      wire('providerAccessFieldsList', 'addProviderAccessFieldBtn', 'providerAccessFieldTemplate', 'data-access-row');
    })();
    </script>
</section>

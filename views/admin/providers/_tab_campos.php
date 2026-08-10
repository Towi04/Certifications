<?php
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
$fieldTypes = [
    'text' => 'Texto',
    'textarea' => 'Texto largo',
    'date' => 'Fecha',
    'number' => 'Número',
    'tel' => 'Teléfono',
    'email' => 'Correo',
    'time' => 'Hora',
    'sex' => 'Sexo',
];
?>
<section class="provider-panel">
    <h3>Campos de adquisición</h3>
    <p class="muted">
        Define qué campos built-in y personalizados estarán disponibles al configurar cada certificación.
        <strong>Nombre, apellido paterno y correo</strong> son siempre obligatorios en el formulario de adquisición.
    </p>

    <form method="post" action="/admin/providers/fields/save" class="form-grid" style="margin-top:1rem">
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
            <legend>Campos personalizados del proveedor</legend>
            <p class="muted">Se podrán activar por certificación (opcional u obligatorio).</p>
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
                        <input name="custom_fields[__i__][label]" required placeholder="Ej. Dirección">
                    </label>
                    <label>Tipo
                        <select name="custom_fields[__i__][type]">
                            <?php foreach ($fieldTypes as $tv => $tl): ?>
                                <option value="<?= e($tv) ?>"><?= e($tl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="check">
                        <input type="checkbox" name="custom_fields[__i__][delete]" value="1"> Eliminar
                    </label>
                </div>
            </template>
        </fieldset>

        <div class="actions field-wide">
            <button class="btn" type="submit">Guardar campos</button>
        </div>
    </form>
    <script>
    (() => {
      const list = document.getElementById('providerCustomFieldsList');
      const btn = document.getElementById('addProviderCustomFieldBtn');
      const tpl = document.getElementById('providerCustomFieldTemplate');
      if (!list || !btn || !tpl) return;
      let idx = list.querySelectorAll('[data-custom-row]').length;
      btn.addEventListener('click', () => {
        const html = tpl.innerHTML.replaceAll('__i__', String(idx++));
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        list.appendChild(wrap.firstElementChild);
      });
    })();
    </script>
</section>

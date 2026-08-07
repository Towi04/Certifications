<?php require __DIR__ . '/../_nav.php'; $item = $item ?? null; ?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <form method="post" action="/admin/protocols/save" class="stack form-grid">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
        <label>Proveedor
            <select name="provider_id">
                <option value="">—</option>
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)($item['provider_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Código<input name="code" required value="<?= e($item['code'] ?? '') ?>"></label>
        <label>Nombre<input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
        <label>Modalidad
            <select name="modality">
                <?php foreach (['online','paper','hybrid','inventory','other'] as $m): ?>
                    <option value="<?= $m ?>" <?= ($item['modality'] ?? 'online') === $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Procedimiento (HTML)<textarea name="procedure_html" rows="8"><?= e($item['procedure_html'] ?? '') ?></textarea></label>
        <label class="check"><input type="checkbox" name="requires_regulation_signature" <?= !empty($item['requires_regulation_signature']) ? 'checked' : '' ?>> Firma reglamento</label>
        <label class="check"><input type="checkbox" name="requires_software" <?= !empty($item['requires_software']) ? 'checked' : '' ?>> Software</label>
        <label class="check"><input type="checkbox" name="requires_zoom" <?= !empty($item['requires_zoom']) ? 'checked' : '' ?>> Zoom</label>
        <label class="check"><input type="checkbox" name="requires_vm" <?= !empty($item['requires_vm']) ? 'checked' : '' ?>> Máquina virtual</label>
        <label class="check"><input type="checkbox" name="uses_inventory" <?= !empty($item['uses_inventory']) ? 'checked' : '' ?>> Inventario</label>
        <label class="check"><input type="checkbox" name="is_active" <?= !isset($item) || (int)($item['is_active'] ?? 1) ? 'checked' : '' ?>> Activo</label>
        <div class="actions"><button class="btn" type="submit">Guardar</button><a class="btn btn-ghost" href="/admin/protocols">Volver</a></div>
    </form>
</section>

<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <form method="post" action="/admin/agreements/save" class="stack form-grid">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
        <label>Nivel TR
            <select name="partner_tier_id" required>
                <?php foreach ($tiers as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)($item['partner_tier_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nombre<input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
        <label>Año<input type="number" name="year" required value="<?= e((string)($item['year'] ?? date('Y'))) ?>"></label>
        <label>Válido desde<input type="date" name="valid_from" required value="<?= e($item['valid_from'] ?? date('Y-01-01')) ?>"></label>
        <label>Válido hasta<input type="date" name="valid_to" value="<?= e($item['valid_to'] ?? '') ?>"></label>
        <label>Notas<textarea name="notes" rows="3"><?= e($item['notes'] ?? '') ?></textarea></label>
        <label class="check"><input type="checkbox" name="is_current" <?= !empty($item['is_current']) ? 'checked' : '' ?>> Convenio vigente de este nivel</label>
        <div class="actions"><button class="btn" type="submit">Guardar</button><a class="btn btn-ghost" href="/admin/agreements">Volver</a></div>
    </form>
</section>

<?php if ($item): ?>
<section class="note">
    <h2>Precios TR</h2>
    <p class="muted">
        Los precios por nivel se capturan en cada certificación (Precio público, Costo Doceo y precios por nivel TR).
        Al agregar un nivel nuevo, aparece automáticamente en todas las fichas de certificación.
    </p>
    <p><a class="btn btn-ghost" href="/admin/certifications">Ir a certificaciones</a></p>
</section>
<?php
$assets = $assets ?? [];
$assetTypes = $assetTypes ?? \App\Catalog\CatalogRepository::assetTypesFor('agreement');
$ownerType = 'agreement';
$ownerId = (int) $item['id'];
$redirect = '/admin/agreements/edit?id=' . $ownerId;
require __DIR__ . '/../_assets.php';
?>
<?php endif; ?>

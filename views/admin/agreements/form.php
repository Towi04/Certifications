<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$prices = $prices ?? [];
$certifications = $certifications ?? [];
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
    <h2>Precios de este convenio</h2>
    <form method="post" action="/admin/agreements/price" class="stack form-grid">
        <input type="hidden" name="agreement_id" value="<?= (int)$item['id'] ?>">
        <label>Certificación
            <select name="certification_id" required>
                <option value="">Selecciona…</option>
                <?php foreach ($certifications as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Precio partner<input type="number" step="0.01" name="price" required></label>
        <button class="btn" type="submit">Guardar precio</button>
    </form>
    <div class="table-wrap" style="margin-top:1rem">
        <table class="data-table">
            <thead><tr><th>Certificación</th><th>Código</th><th>Precio</th></tr></thead>
            <tbody>
            <?php foreach ($prices as $p): ?>
                <tr>
                    <td><?= e($p['certification_name']) ?></td>
                    <td><code><?= e($p['certification_code']) ?></code></td>
                    <td><?= e(\App\Support\Str::money((float)$p['price'], $p['currency'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$prices): ?><tr><td colspan="3">Sin precios aún.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

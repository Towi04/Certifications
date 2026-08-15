<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$protocols = $protocols ?? [];
$tab = $_GET['tab'] ?? 'general';
$tabs = [
    'general' => 'General',
    'acceso' => 'Acceso',
];
if ($item) {
    $tabs['assets'] = 'Archivos';
}
if (!isset($tabs[$tab])) {
    $tab = 'general';
}
?>
<section class="admin-ficha" data-admin-ficha data-tab="<?= e($tab) ?>">
<?php
$fichaTitle = $item['name'] ?? 'Nuevo curso';
$fichaSubtitle = $item ? ($item['code'] ?? null) : null;
$fichaBackUrl = '/admin/courses';
$fichaMode = 'js';
$fichaTabBase = '';
require __DIR__ . '/../_ficha_head.php';
?>

    <form method="post" action="/admin/courses/save" class="stack">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>

        <div class="admin-ficha-panel" data-tab-panel="general" <?= $tab !== 'general' ? 'hidden' : '' ?>>
            <h3>Datos del curso</h3>
            <div class="form-grid">
            <label>Código<input name="code" required value="<?= e($item['code'] ?? '') ?>"></label>
            <label>Nombre<input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
            <label>Slug (URL)
                <input name="slug" value="<?= e($item['slug'] ?? '') ?>" placeholder="auto desde el nombre">
            </label>
            <label>Protocolo
                <select name="protocol_id">
                    <option value="">— Auto según plataforma —</option>
                    <?php foreach ($protocols as $pr): ?>
                        <option value="<?= (int)$pr['id'] ?>" <?= (int)($item['protocol_id'] ?? 0) === (int)$pr['id'] ? 'selected' : '' ?>>
                            <?= e($pr['code']) ?> · <?= e($pr['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="muted" style="font-weight:400;display:block;margin-top:0.25rem">
                    Moodle → COURSE_MOODLE · eThinking → COURSE_ETHINKING · XperienceEd → COURSE_XPERIENCEED
                </span>
            </label>
            <label>Precio público (MXN)
                <input type="number" step="0.01" min="0" name="public_price" value="<?= e((string)($item['public_price'] ?? '')) ?>" placeholder="Ej. 1500">
            </label>
            <label class="field-wide">Descripción<textarea name="description" rows="4"><?= e($item['description'] ?? '') ?></textarea></label>
            <label class="check"><input type="checkbox" name="is_active" <?= !isset($item) || (int)($item['is_active'] ?? 1) ? 'checked' : '' ?>> Activo</label>
            <label class="check"><input type="checkbox" name="is_published" <?= (int)($item['is_published'] ?? 0) ? 'checked' : '' ?>> Publicado en catálogo (vendible)</label>
            <label class="check"><input type="checkbox" name="standalone" <?= (int)($item['standalone'] ?? 0) ? 'checked' : '' ?>> No requiere certificación</label>
            <p class="muted" style="margin:0">Marca “No requiere certificación” si se vende solo. Con precio + publicado aparece en /#cursos con botón Adquirir.</p>
            </div>
        </div>

        <div class="admin-ficha-panel" data-tab-panel="acceso" <?= $tab !== 'acceso' ? 'hidden' : '' ?>>
            <h3>Acceso y plataforma</h3>
            <div class="form-grid">
            <label>Plataforma
                <select name="platform_type">
                    <?php foreach (['moodle','xperienceed','ethinking','external','internal','none'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($item['platform_type'] ?? 'moodle') === $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>URL externa<input name="external_url" value="<?= e($item['external_url'] ?? '') ?>"></label>
            <label>Moodle course ID<input name="moodle_course_id" value="<?= e((string)($item['moodle_course_id'] ?? '')) ?>"></label>
            <label>Meses de acceso
                <input type="number" name="access_months" min="1" max="36" value="<?= e((string)($item['access_months'] ?? '6')) ?>">
                <span class="muted" style="font-weight:400;display:block;margin-top:0.25rem">Al otorgar acceso desde el PDV (default 6).</span>
            </label>
            <label>Costo prórroga (MXN)
                <input type="number" step="0.01" min="0" name="prorroga_price" value="<?= e((string)($item['prorroga_price'] ?? '')) ?>" placeholder="Ej. 500">
                <span class="muted" style="font-weight:400;display:block;margin-top:0.25rem">La prórroga siempre suma 6 meses más de acceso Moodle.</span>
            </label>
            <label class="field-wide">Notas de acceso<textarea name="access_notes" rows="3"><?= e($item['access_notes'] ?? '') ?></textarea></label>
            </div>
        </div>

        <div class="admin-ficha-actions">
            <button class="btn" type="submit">Guardar</button>
            <a class="btn btn-ghost" href="/admin/courses">Volver</a>
        </div>
    </form>

    <?php if ($item): ?>
        <div class="admin-ficha-panel" data-tab-panel="assets" <?= $tab !== 'assets' ? 'hidden' : '' ?>>
            <?php
            $assets = $assets ?? [];
            $assetTypes = $assetTypes ?? \App\Catalog\CatalogRepository::assetTypesFor('course');
            $ownerType = 'course';
            $ownerId = (int) $item['id'];
            $redirect = '/admin/courses/edit?id=' . (int) $item['id'] . '&tab=assets';
            require __DIR__ . '/../_assets.php';
            ?>
        </div>
        <form method="post" action="/admin/courses/delete" style="margin-top:1rem"
              onsubmit="return confirm(<?= json_encode('¿Eliminar el curso “' . ($item['name'] ?? '') . '”? Si tiene matrículas Moodle, solo se desactivará.', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);">
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <button class="btn btn-ghost" type="submit">Eliminar curso</button>
        </form>
    <?php endif; ?>
</section>

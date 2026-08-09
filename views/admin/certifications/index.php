<?php
require __DIR__ . '/../_nav.php';
$iconEye = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.8"/></svg>';
$iconEyeOff = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 3l18 18M10.5 10.6A3.2 3.2 0 0 0 13.4 13.5M9.9 5.2C10.6 5.1 11.3 5 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-4.2 4.8M6.1 6.1A17.4 17.4 0 0 0 2 12s3.5 7 10 7c1.3 0 2.5-.3 3.6-.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconEdit = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M13.5 6.5l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Certificaciones</h2>
            <p class="muted" style="margin:0.35rem 0 0">Se dan de alta desde Proveedores. Aquí editas la ficha y publicas/ocultas.</p>
        </div>
        <div class="actions">
            <a class="btn" href="/admin/certifications/pricing">Precios y reglamentos (masivo)</a>
        </div>
    </div>
    <form method="get" class="filters stack form-grid" style="margin-top:1rem">
        <label>Buscar<input name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="nombre o código"></label>
        <label>Proveedor
            <select name="provider_id">
                <option value="">Todos</option>
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (string)($filters['provider_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Publicada
            <select name="is_published">
                <option value="">Todas</option>
                <option value="1" <?= ($filters['is_published'] ?? '') === '1' ? 'selected' : '' ?>>Sí</option>
                <option value="0" <?= ($filters['is_published'] ?? '') === '0' ? 'selected' : '' ?>>No</option>
            </select>
        </label>
        <button class="btn" type="submit">Filtrar</button>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Proveedor</th>
                    <th>Modalidad</th>
                    <th>Habilidades</th>
                    <th>Precio público</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $pub = (int)$item['is_published'] === 1; ?>
                <tr class="<?= $pub ? '' : 'is-row-inactive' ?>">
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($item['provider_name']) ?></td>
                    <td><?= \App\Support\CertIcons::modalityHtml((string)($item['modality'] ?? '')) ?></td>
                    <td>
                        <?php
                        $skillsIcons = \App\Support\CertIcons::skillsHtml(
                            $item['skills_json'] ?? null,
                            !empty($item['is_level_exam'])
                        );
                        echo $skillsIcons !== '' ? $skillsIcons : '<span class="muted">—</span>';
                        ?>
                    </td>
                    <td><?= e(\App\Support\Str::money(isset($item['public_price']) ? (float)$item['public_price'] : null, $item['currency'] ?? 'MXN')) ?></td>
                    <td>
                        <div class="icon-actions">
                            <form method="post" action="/admin/certifications/toggle-published" class="inline-form"
                                  onsubmit="return confirm(<?= json_encode(($pub ? '¿Ocultar' : '¿Publicar') . ' “' . $item['name'] . '”?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <input type="hidden" name="redirect" value="/admin/certifications">
                                <button type="submit" class="icon-btn eye-btn" title="<?= $pub ? 'Ocultar' : 'Publicar' ?>" aria-label="<?= $pub ? 'Ocultar' : 'Publicar' ?>">
                                    <?= $pub ? $iconEye : $iconEyeOff ?>
                                </button>
                            </form>
                            <a class="icon-btn" href="/admin/certifications/edit?id=<?= (int)$item['id'] ?>" title="Editar" aria-label="Editar"><?= $iconEdit ?></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <tr><td colspan="6" class="muted">No hay certificaciones. Agrégalas desde Proveedores.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

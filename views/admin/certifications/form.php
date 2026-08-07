<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$linkedCourses = $linkedCourses ?? [];
?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <form method="post" action="/admin/certifications/save" class="stack form-grid">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
        <label>Proveedor
            <select name="provider_id" required>
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)($item['provider_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Protocolo
            <select name="protocol_id">
                <option value="">—</option>
                <?php foreach ($protocols as $pr): ?>
                    <option value="<?= (int)$pr['id'] ?>" <?= (int)($item['protocol_id'] ?? 0) === (int)$pr['id'] ? 'selected' : '' ?>><?= e($pr['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Código<input name="code" required value="<?= e($item['code'] ?? '') ?>"></label>
        <label>Slug<input name="slug" value="<?= e($item['slug'] ?? '') ?>" placeholder="auto desde nombre"></label>
        <label>Nombre<input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
        <label>Modalidad
            <select name="modality">
                <?php foreach (['online','paper','hybrid','other'] as $m): ?>
                    <option value="<?= $m ?>" <?= ($item['modality'] ?? 'online') === $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Resumen<textarea name="short_description" rows="3"><?= e($item['short_description'] ?? '') ?></textarea></label>
        <label>Descripción HTML<textarea name="description_html" rows="5"><?= e($item['description_html'] ?? '') ?></textarea></label>
        <label>Temario HTML<textarea name="syllabus_html" rows="5"><?= e($item['syllabus_html'] ?? '') ?></textarea></label>
        <label>Duración<input name="duration_label" value="<?= e($item['duration_label'] ?? '') ?>"></label>
        <label>Audiencia<input name="audience" value="<?= e($item['audience'] ?? '') ?>"></label>
        <label>Precio público<input type="number" step="0.01" name="public_price" value="<?= e((string)($item['public_price'] ?? '')) ?>"></label>
        <label>Moneda<input name="currency" value="<?= e($item['currency'] ?? 'MXN') ?>"></label>
        <label class="check"><input type="checkbox" name="cenni_eligible" <?= !empty($item['cenni_eligible']) ? 'checked' : '' ?>> Elegible CENNI</label>
        <label>Tipo doc CENNI
            <select name="cenni_doc_type">
                <?php foreach (['none','constancia','certificado','diploma'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($item['cenni_doc_type'] ?? 'none') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="check"><input type="checkbox" name="cenni_included" <?= !empty($item['cenni_included']) ? 'checked' : '' ?>> CENNI incluido</label>
        <label>Fee CENNI<input type="number" step="0.01" name="cenni_fee" value="<?= e((string)($item['cenni_fee'] ?? '')) ?>"></label>
        <label class="check"><input type="checkbox" name="conocer_eligible" <?= !empty($item['conocer_eligible']) ? 'checked' : '' ?>> Elegible CONOCER</label>
        <label>Fee CONOCER<input type="number" step="0.01" name="conocer_fee" value="<?= e((string)($item['conocer_fee'] ?? '')) ?>"></label>
        <label>Orden<input type="number" name="sort_order" value="<?= e((string)($item['sort_order'] ?? '0')) ?>"></label>
        <label class="check"><input type="checkbox" name="is_published" <?= !empty($item['is_published']) ? 'checked' : '' ?>> Publicada (visible a partners)</label>
        <div class="actions"><button class="btn" type="submit">Guardar</button><a class="btn btn-ghost" href="/admin/certifications">Volver</a></div>
    </form>

    <?php if ($item): ?>
        <h3>Cursos vinculados</h3>
        <?php if ($linkedCourses): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Curso</th><th>Relación</th><th>Plataforma</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($linkedCourses as $c): ?>
                        <tr>
                            <td><?= e($c['course_name']) ?></td>
                            <td><?= e($c['relation_type']) ?></td>
                            <td><?= e($c['platform_type']) ?></td>
                            <td>
                                <form method="post" action="/admin/certifications/detach-course" class="inline-form">
                                    <input type="hidden" name="certification_id" value="<?= (int)$item['id'] ?>">
                                    <input type="hidden" name="course_id" value="<?= (int)$c['course_id'] ?>">
                                    <button type="submit" class="linkish">Quitar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">Ningún curso vinculado aún.</p>
        <?php endif; ?>

        <form method="post" action="/admin/certifications/attach-course" class="stack form-grid" style="margin-top:1rem">
            <input type="hidden" name="certification_id" value="<?= (int)$item['id'] ?>">
            <label>Vincular curso
                <select name="course_id" required>
                    <option value="">—</option>
                    <?php foreach (($courses ?? []) as $course): ?>
                        <option value="<?= (int)$course['id'] ?>"><?= e($course['name']) ?> (<?= e($course['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Relación
                <select name="relation_type">
                    <?php foreach (['included', 'sold_separate', 'bundle_discount'] as $rel): ?>
                        <option value="<?= $rel ?>"><?= $rel ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Precio bundle<input type="number" step="0.01" name="bundle_price"></label>
            <label>Notas<input name="notes"></label>
            <div class="actions"><button class="btn" type="submit">Vincular curso</button></div>
        </form>
    <?php endif; ?>
</section>

<?php if ($item): ?>
<?php
$assets = $assets ?? [];
$assetTypes = $assetTypes ?? \App\Catalog\CatalogRepository::assetTypesFor('certification');
$ownerType = 'certification';
$ownerId = (int) $item['id'];
$redirect = '/admin/certifications/edit?id=' . $ownerId;
require __DIR__ . '/../_assets.php';
?>
<?php endif; ?>


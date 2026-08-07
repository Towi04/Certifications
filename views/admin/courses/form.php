<?php require __DIR__ . '/../_nav.php'; $item = $item ?? null; ?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <form method="post" action="/admin/courses/save" class="stack form-grid">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
        <label>Código<input name="code" required value="<?= e($item['code'] ?? '') ?>"></label>
        <label>Nombre<input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
        <label>Plataforma
            <select name="platform_type">
                <?php foreach (['moodle','xperienceed','ethinking','external','internal','none'] as $p): ?>
                    <option value="<?= $p ?>" <?= ($item['platform_type'] ?? 'moodle') === $p ? 'selected' : '' ?>><?= $p ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>URL externa<input name="external_url" value="<?= e($item['external_url'] ?? '') ?>"></label>
        <label>Moodle course ID<input name="moodle_course_id" value="<?= e((string)($item['moodle_course_id'] ?? '')) ?>"></label>
        <label>Notas de acceso<textarea name="access_notes" rows="3"><?= e($item['access_notes'] ?? '') ?></textarea></label>
        <label>Descripción<textarea name="description" rows="4"><?= e($item['description'] ?? '') ?></textarea></label>
        <label class="check"><input type="checkbox" name="is_active" <?= !isset($item) || (int)($item['is_active'] ?? 1) ? 'checked' : '' ?>> Activo</label>
        <div class="actions"><button class="btn" type="submit">Guardar</button><a class="btn btn-ghost" href="/admin/courses">Volver</a></div>
    </form>
</section>

<?php require __DIR__ . '/../_nav.php'; $item = $item ?? null; $protocols = $protocols ?? []; ?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <form method="post" action="/admin/courses/save" class="stack form-grid">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
        <label>Código<input name="code" required value="<?= e($item['code'] ?? '') ?>"></label>
        <label>Nombre<input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
        <label>Protocolo (opcional)
            <select name="protocol_id">
                <option value="">— Sin protocolo —</option>
                <?php foreach ($protocols as $pr): ?>
                    <option value="<?= (int)$pr['id'] ?>" <?= (int)($item['protocol_id'] ?? 0) === (int)$pr['id'] ? 'selected' : '' ?>>
                        <?= e($pr['code']) ?> · <?= e($pr['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
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
        <label>Notas de acceso<textarea name="access_notes" rows="3"><?= e($item['access_notes'] ?? '') ?></textarea></label>
        <label>Descripción<textarea name="description" rows="4"><?= e($item['description'] ?? '') ?></textarea></label>
        <label class="check"><input type="checkbox" name="is_active" <?= !isset($item) || (int)($item['is_active'] ?? 1) ? 'checked' : '' ?>> Activo</label>
        <div class="actions"><button class="btn" type="submit">Guardar</button><a class="btn btn-ghost" href="/admin/courses">Volver</a></div>
    </form>
</section>

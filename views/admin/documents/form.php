<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$docTypes = $docTypes ?? [];
$providers = $providers ?? [];
?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <p class="muted">Sube reglamentos u otros archivos que debas enviar a los alumnos. Al actualizar, cambia la versión (ej. 1.0 → 1.1).</p>
    <form method="post" action="/admin/documents/save" enctype="multipart/form-data" class="stack form-grid">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>

        <label>Nombre
            <input name="title" required value="<?= e($item['title'] ?? '') ?>" placeholder="Reglamento ELET">
        </label>
        <label>Versión
            <input name="version" required value="<?= e($item['version'] ?? '1.0') ?>" placeholder="1.0">
        </label>
        <label>Proveedor
            <select name="provider_id" required>
                <option value="">— Selecciona —</option>
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)($item['provider_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tipo
            <select name="doc_type">
                <?php foreach ($docTypes as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($item['doc_type'] ?? 'regulation') === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Código interno (opcional)
            <input name="code" value="<?= e($item['code'] ?? '') ?>" placeholder="Se genera solo si lo dejas vacío">
        </label>
        <label>Archivo PDF <?= $item ? '(dejar vacío para conservar el actual)' : '' ?>
            <input type="file" name="file" accept=".pdf,application/pdf" <?= $item ? '' : 'required' ?>>
        </label>
        <?php if (!empty($item['file_path'])): ?>
            <p class="muted" style="grid-column:1/-1">
                Actual:
                <a href="/media?f=<?= e(rawurlencode($item['file_path'])) ?>" target="_blank" rel="noopener">ver archivo</a>
            </p>
        <?php endif; ?>
        <label style="grid-column:1/-1">Notas internas (opcional)
            <textarea name="notes" rows="3"><?= e($item['body_html'] ?? '') ?></textarea>
        </label>
        <label class="check">
            <input type="checkbox" name="is_active" <?= !isset($item) || (int)($item['is_active'] ?? 1) ? 'checked' : '' ?>>
            Activo
        </label>
        <div class="actions" style="grid-column:1/-1">
            <button class="btn" type="submit">Guardar</button>
            <a class="btn btn-ghost" href="/admin/documents">Volver</a>
        </div>
    </form>
</section>

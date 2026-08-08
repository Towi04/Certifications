<?php
require __DIR__ . '/../_nav.php';
$docTypes = $docTypes ?? [];
$providers = $providers ?? [];
$providerFilter = $providerFilter ?? null;
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Documentos</h2>
            <p class="muted" style="margin:0.35rem 0 0">
                Reglamentos, formularios e instrucciones para alumnos. Cada archivo tiene versión y proveedor.
            </p>
        </div>
        <a class="btn" href="/admin/documents/create">Subir documento</a>
    </div>

    <form method="get" action="/admin/documents" class="stack form-grid" style="margin-top:1rem">
        <label>Filtrar por proveedor
            <select name="provider_id" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)$providerFilter === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Versión</th>
                <th>Proveedor</th>
                <th>Tipo</th>
                <th>Archivo</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$items): ?>
                <tr><td colspan="6" class="muted">Aún no hay documentos. Sube el primero.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <strong><?= e($item['title']) ?></strong>
                        <?php if (!(int)($item['is_active'] ?? 1)): ?>
                            <span class="pill">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= e($item['version']) ?></code></td>
                    <td><?= e($item['provider_name'] ?? '—') ?></td>
                    <td><?= e($docTypes[$item['doc_type']] ?? $item['doc_type']) ?></td>
                    <td>
                        <?php if (!empty($item['file_path'])): ?>
                            <a href="/media?f=<?= e(rawurlencode($item['file_path'])) ?>" target="_blank" rel="noopener">Ver / descargar</a>
                        <?php else: ?>
                            <span class="muted">Sin archivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a href="/admin/documents/edit?id=<?= (int)$item['id'] ?>">Editar</a>
                        <form method="post" action="/admin/documents/delete" style="display:inline" onsubmit="return confirm('¿Eliminar este documento?');">
                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                            <button class="btn btn-ghost" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

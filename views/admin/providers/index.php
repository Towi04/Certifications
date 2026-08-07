<?php require __DIR__ . '/_nav.php'; ?>
<section class="note">
    <div class="page-head" style="margin:0">
        <h2 style="margin:0">Proveedores</h2>
        <a class="btn" href="/admin/providers/create">Nuevo</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Código</th><th>Nombre</th><th>Activo</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><code><?= e($item['code']) ?></code></td>
                    <td><?= e($item['name']) ?></td>
                    <td><?= (int)$item['is_active'] ? 'Sí' : 'No' ?></td>
                    <td><a href="/admin/providers/edit?id=<?= (int)$item['id'] ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

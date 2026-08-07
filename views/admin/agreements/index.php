<?php require __DIR__ . '/../_nav.php'; ?>
<section class="note">
    <div class="page-head" style="margin:0">
        <h2 style="margin:0">Versiones anuales de convenio</h2>
        <a class="btn" href="/admin/agreements/create">Nueva</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Año</th><th>Nombre</th><th>Nivel</th><th>Vigente</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= (int)$item['year'] ?></td>
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($item['tier_name']) ?></td>
                    <td><?= (int)$item['is_current'] ? 'Sí' : 'No' ?></td>
                    <td><a href="/admin/agreements/edit?id=<?= (int)$item['id'] ?>">Editar / precios</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

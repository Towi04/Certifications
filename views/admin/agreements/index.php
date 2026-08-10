<?php require __DIR__ . '/../_nav.php'; ?>
<section class="note">
    <div class="page-head" style="margin:0">
        <h2 style="margin:0">Versiones de convenio TR</h2>
        <a class="btn" href="/admin/agreements/create">Nueva versión</a>
    </div>
    <p class="muted">
        Cada versión pertenece a un nivel. Al publicarla se asigna a todos los partners de ese nivel
        para que firmen dentro del plazo.
    </p>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Año</th>
                <th>Nombre</th>
                <th>Nivel</th>
                <th>Vigente</th>
                <th>Partners nivel</th>
                <th>Firmas pendientes</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= (int)$item['year'] ?></td>
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($item['tier_name']) ?></td>
                    <td><?= (int)$item['is_current'] ? 'Sí' : 'No' ?></td>
                    <td><?= (int)($item['partners_in_tier'] ?? 0) ?></td>
                    <td><?= (int)($item['pending_signatures'] ?? 0) ?></td>
                    <td><a href="/admin/agreements/edit?id=<?= (int)$item['id'] ?>">Editar / publicar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <tr><td colspan="7" class="muted">Aún no hay versiones. Crea la primera.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

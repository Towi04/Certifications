<?php
require __DIR__ . '/../_nav.php';
$partnersTab = 'niveles';
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <h2 style="margin:0">Partners Teacher Referral</h2>
        <a class="btn" href="/admin/tiers/create">Nuevo nivel</a>
    </div>
    <?php require __DIR__ . '/../partners/_tabs.php'; ?>
    <p class="muted" style="margin:0 0 1rem">
        Los niveles definen el convenio/precio TR. Al crear uno nuevo, aparece en la matriz de precios de cada certificación.
    </p>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Orden</th><th>Código</th><th>Nombre</th><th>Activo</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= (int)$item['sort_order'] ?></td>
                    <td><code><?= e($item['code']) ?></code></td>
                    <td><?= e($item['name']) ?></td>
                    <td><?= (int)$item['is_active'] ? 'Sí' : 'No' ?></td>
                    <td><a href="/admin/tiers/edit?id=<?= (int)$item['id'] ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <tr><td colspan="5" class="muted">Aún no hay niveles. Crea el primero.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

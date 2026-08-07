<?php require __DIR__ . '/../_nav.php'; ?>
<section class="note">
    <div class="page-head" style="margin:0">
        <h2 style="margin:0">Partners Teacher Referral</h2>
        <a class="btn" href="/admin/partners/create">Nuevo partner</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Partner</th>
                <th>Organización</th>
                <th>Nivel</th>
                <th>Convenio</th>
                <th>Ciudad envío</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?= e($item['user_name']) ?><br>
                        <small class="muted"><?= e($item['email']) ?></small>
                    </td>
                    <td><?= e($item['organization'] ?? '—') ?></td>
                    <td><?= e($item['tier_name'] ?? '—') ?></td>
                    <td><?= e($item['agreement_name'] ?? '—') ?></td>
                    <td><?= e($item['shipping_city'] ?? '—') ?></td>
                    <td><a href="/admin/partners/edit?id=<?= (int)$item['id'] ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <tr><td colspan="6" class="muted">Aún no hay partners. Crea uno con “Nuevo partner”.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

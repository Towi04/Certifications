<?php
require __DIR__ . '/../_nav.php';
$partnersTab = 'partners';
$statusLabels = [
    'pending' => 'Pendiente firma',
    'submitted' => 'En revisión',
    'approved' => 'OK',
    'rejected' => 'Rechazado',
    'expired' => 'Vencido',
];
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <h2 style="margin:0">Partners Teacher Referral</h2>
        <a class="btn" href="/admin/partners/create">Nuevo partner</a>
    </div>
    <?php require __DIR__ . '/_tabs.php'; ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Partner</th>
                <th>Organización</th>
                <th>Nivel</th>
                <th>Convenio</th>
                <th>Firma / acceso</th>
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
                    <td>
                        <?php if (!empty($item['access_restricted'])): ?>
                            <strong>Restringido</strong>
                            <?php if (!empty($item['signature_status'])): ?>
                                <br><small class="muted"><?= e($statusLabels[$item['signature_status']] ?? $item['signature_status']) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            Completo
                            <?php if (!empty($item['signature_status']) && $item['signature_status'] !== 'approved'): ?>
                                <br><small class="muted"><?= e($statusLabels[$item['signature_status']] ?? $item['signature_status']) ?></small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= e($item['shipping_city'] ?? '—') ?></td>
                    <td><a href="/admin/partners/edit?id=<?= (int)$item['id'] ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <tr><td colspan="7" class="muted">Aún no hay partners. Crea uno con “Nuevo partner”.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

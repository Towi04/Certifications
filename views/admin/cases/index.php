<?php require __DIR__ . '/../_nav.php'; ?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Casos de certificación</h2>
            <p class="muted" style="margin:0.35rem 0 0">Seguimiento del alumno en el protocolo (paso actual).</p>
        </div>
        <a class="btn" href="/admin/cases/create">Abrir caso</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Alumno</th>
                <th>Certificación</th>
                <th>Paso actual</th>
                <th>Examen</th>
                <th>Estado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$items): ?>
                <tr><td colspan="7" class="muted">No hay casos. Ábrelo cuando un alumno inicie el proceso.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>#<?= (int)$item['id'] ?></td>
                    <td>
                        <?= e($item['student_name']) ?><br>
                        <span class="muted"><?= e($item['student_email']) ?></span>
                    </td>
                    <td><?= e($item['certification_code'] ?? '') ?> · <?= e($item['certification_name'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($item['current_step_title'])): ?>
                            #<?= (int)($item['current_step_order'] ?? 0) ?> <?= e($item['current_step_title']) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= e($item['exam_date'] ?? '—') ?></td>
                    <td><?= e($item['status']) ?></td>
                    <td><a href="/admin/cases/view?id=<?= (int)$item['id'] ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

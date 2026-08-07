<?php
require __DIR__ . '/../_nav.php';
$phases = $phases ?? [];
$responsibles = $responsibles ?? [];
$statusLabels = [
    'pending' => 'Pendiente',
    'current' => 'En curso',
    'done' => 'Hecho',
    'skipped' => 'Omitido',
    'blocked' => 'Bloqueado',
];
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0"><?= e($title) ?></h2>
            <p class="muted" style="margin:0.35rem 0 0">
                <?= e($item['student_name']) ?> · <?= e($item['student_email']) ?><br>
                <?= e($item['certification_code']) ?> · <?= e($item['certification_name']) ?> ·
                Protocolo: <?= e($item['protocol_name']) ?> ·
                Estado: <?= e($item['status']) ?>
                <?php if (!empty($item['exam_date'])): ?> · Examen: <?= e($item['exam_date']) ?><?php endif; ?>
            </p>
        </div>
        <a class="btn btn-ghost" href="/admin/cases">Volver</a>
    </div>

    <ol class="protocol-timeline protocol-timeline--progress">
        <?php
        $lastPhase = null;
        foreach ($steps as $step):
            $phase = (string) $step['phase'];
            if ($phase !== $lastPhase):
                $lastPhase = $phase;
        ?>
            <li class="protocol-phase-label"><?= e($phases[$phase] ?? $phase) ?></li>
        <?php endif; ?>
            <li class="protocol-step status-<?= e($step['status']) ?>">
                <div class="protocol-step-head">
                    <span class="protocol-step-num"><?= (int)$step['sort_order'] ?></span>
                    <strong><?= e($step['title']) ?></strong>
                    <span class="pill"><?= e($statusLabels[$step['status']] ?? $step['status']) ?></span>
                    <span class="pill"><?= e($responsibles[$step['responsible']] ?? $step['responsible']) ?></span>
                </div>
                <?php if (!empty($step['description'])): ?>
                    <p class="muted"><?= e($step['description']) ?></p>
                <?php endif; ?>
                <?php if ($step['status'] === 'current' && $item['status'] === 'in_progress'): ?>
                    <form method="post" action="/admin/cases/complete-step" class="stack" style="margin-top:0.75rem">
                        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
                        <input type="hidden" name="case_step_id" value="<?= (int)$step['id'] ?>">
                        <label>Nota (opcional)<input name="notes" placeholder="Ej. correo enviado a UKS"></label>
                        <button class="btn" type="submit">Marcar paso como hecho →</button>
                    </form>
                <?php elseif ($step['status'] === 'done' && !empty($step['completed_at'])): ?>
                    <p class="muted">Completado: <?= e($step['completed_at']) ?></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<?php
$item = $item ?? [];
$steps = $steps ?? [];
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
<section class="page-head">
    <div>
        <h1><?= e($item['certification_name'] ?? ('Caso #' . ($item['id'] ?? ''))) ?></h1>
        <p class="muted">
            Protocolo: <?= e($item['protocol_name'] ?? '') ?> ·
            Estado: <?= e($item['status'] ?? '') ?>
        </p>
    </div>
    <a class="btn btn-ghost" href="/alumno">Mis certificaciones</a>
</section>

<?php if (!empty($info)): ?><div class="alert alert-ok"><?= e($info) ?></div><?php endif; ?>

<section class="note">
    <p class="muted">Este timeline te muestra en qué paso va tu certificación. Algunos pasos los completa el equipo Doceo, el TR o la certificadora.</p>
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
            </li>
        <?php endforeach; ?>
    </ol>
</section>

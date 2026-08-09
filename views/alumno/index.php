<?php
$user = $user ?? [];
$cases = $cases ?? [];
?>
<section class="page-head">
    <div>
        <h1>Mi seguimiento</h1>
        <p class="muted">Hola, <?= e($user['name'] ?? $user['email'] ?? '') ?>. Aquí ves el avance de tus certificaciones.</p>
    </div>
    <a class="btn btn-ghost" href="/">Catálogo</a>
</section>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (!empty($info)): ?><div class="alert alert-ok"><?= e($info) ?></div><?php endif; ?>

<section class="note">
    <?php if (!$cases): ?>
        <p class="muted">Aún no tienes certificaciones en seguimiento. <a href="/">Explora el catálogo</a> y pulsa Adquirir.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Certificación</th>
                    <th>Paso actual</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($cases as $c): ?>
                    <tr>
                        <td>
                            <strong><?= e($c['certification_name']) ?></strong><br>
                            <span class="muted"><?= e($c['certification_code']) ?></span>
                        </td>
                        <td>
                            <?php if (!empty($c['current_step_title'])): ?>
                                #<?= (int)($c['current_step_order'] ?? 0) ?> <?= e($c['current_step_title']) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php
                            $st = (string) ($c['status'] ?? '');
                            echo e(match ($st) {
                                'in_progress' => 'En progreso',
                                'completed' => 'Completado',
                                'cancelled' => 'Cancelado',
                                default => $st !== '' ? $st : '—',
                            });
                        ?></td>
                        <td><a href="/alumno/caso?id=<?= (int)$c['id'] ?>">Continuar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

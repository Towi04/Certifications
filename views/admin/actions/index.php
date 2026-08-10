<?php
require __DIR__ . '/../_nav.php';
$items = $items ?? [];
$handlers = $handlers ?? \App\Workflow\ActionRepository::handlers();
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Acciones</h2>
            <p class="muted" style="margin:0.35rem 0 0">
                Catálogo reutilizable. Los protocolos solo eligen y ordenan estas acciones.
                Cada una puede ser botón manual y/o dispararse sola (pago, registro, datos de acceso).
            </p>
        </div>
        <a class="btn" href="/admin/actions/create">Nueva acción</a>
    </div>
    <div class="table-wrap" style="margin-top:1rem">
        <table class="data-table">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Código</th>
                <th>Qué hace</th>
                <th>Botón</th>
                <th>Triggers</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$items): ?>
                <tr><td colspan="6" class="muted">Sin acciones. Se crean las básicas al abrir esta pantalla.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
                <?php
                $triggers = [];
                $raw = $item['auto_triggers'] ?? null;
                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    $triggers = is_array($decoded) ? $decoded : [];
                }
                ?>
                <tr class="<?= (int)($item['is_active'] ?? 1) ? '' : 'is-row-inactive' ?>">
                    <td>
                        <strong><?= e($item['name']) ?></strong>
                        <?php if (!empty($item['description'])): ?>
                            <br><small class="muted"><?= e($item['description']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><code><?= e($item['code']) ?></code></td>
                    <td><?= e($handlers[$item['handler'] ?? ''] ?? ($item['handler'] ?? '')) ?>
                        <?php if (!empty($item['mail_template_code'])): ?>
                            <br><code class="muted"><?= e($item['mail_template_code']) ?></code>
                        <?php endif; ?>
                    </td>
                    <td><?= !empty($item['show_as_button']) ? e($item['button_label'] ?: $item['name']) : '—' ?></td>
                    <td>
                        <?php if ($triggers): ?>
                            <?= e(implode(', ', $triggers)) ?>
                        <?php else: ?>
                            <span class="muted">solo manual</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="/admin/actions/edit?id=<?= (int)$item['id'] ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php
require __DIR__ . '/../_nav.php';
$items = $items ?? [];
$audience = $audience ?? '';
$audienceLabels = [
    'student' => 'Alumno',
    'provider' => 'Proveedor',
    'internal' => 'Interno',
    'other' => 'Otro',
];
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Plantillas de correo</h2>
            <p class="muted" style="margin:0.35rem 0 0">
                Edita textos a alumnos y proveedores. Para pruebas, en plantillas de proveedor cambia
                <strong>Destino fijo</strong> a tu correo antes de mandar solicitudes reales.
            </p>
        </div>
        <a class="btn" href="/admin/mail-templates/create">Nueva</a>
    </div>
    <form method="get" class="filters stack form-grid" style="margin-top:1rem">
        <label>Audiencia
            <select name="audience">
                <option value="">Todas</option>
                <?php foreach ($audienceLabels as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $audience === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn" type="submit">Filtrar</button>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Audiencia</th>
                    <th>Destino</th>
                    <th>Asunto</th>
                    <th>Activa</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr class="<?= (int)($item['is_active'] ?? 0) === 1 ? '' : 'is-row-inactive' ?>">
                    <td><code><?= e($item['code']) ?></code></td>
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($audienceLabels[$item['audience'] ?? ''] ?? ($item['audience'] ?? '')) ?></td>
                    <td>
                        <?= e((string)($item['to_mode'] ?? '')) ?>
                        <?php if (!empty($item['to_fixed'])): ?>
                            <br><span class="muted"><?= e($item['to_fixed']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($item['subject']) ?></td>
                    <td><?= (int)($item['is_active'] ?? 0) === 1 ? 'Sí' : 'No' ?></td>
                    <td><a href="/admin/mail-templates/edit?id=<?= (int)$item['id'] ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <tr><td colspan="7" class="muted">No hay plantillas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

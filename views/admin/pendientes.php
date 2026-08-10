<?php
require __DIR__ . '/_nav.php';
$items = $items ?? [];
$filter = (string) ($filter ?? 'needs_admin');
$filters = $filters ?? \App\Catalog\CatalogRepository::caseAttentionFilters();
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Pendientes operativos</h2>
            <p class="muted" style="margin:0.35rem 0 0">
                Casos que requieren seguimiento: pago, solicitud al proveedor, datos de acceso o CENNI.
            </p>
        </div>
        <a class="btn btn-ghost" href="/admin/cases">Ver todos los casos</a>
    </div>

    <form method="get" class="filters stack form-grid" style="margin-top:1rem">
        <label class="field-wide">Filtro de estatus
            <select name="filter" onchange="this.form.submit()">
                <?php foreach ($filters as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $filter === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Caso</th>
                <th>Alumno</th>
                <th>Certificación</th>
                <th>Etapa</th>
                <th>Qué falta</th>
                <th>Actualizado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$items): ?>
                <tr><td colspan="7" class="muted">No hay casos con este filtro.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
                <?php
                $full = trim(
                    (string) ($item['student_name'] ?? '') . ' '
                    . (string) ($item['student_last_name_p'] ?? '') . ' '
                    . (string) ($item['student_last_name_m'] ?? '')
                );
                $tone = !empty($item['needs_admin']) ? 'pill-warn' : 'pill-muted';
                if (($item['attention_key'] ?? '') === 'completed') {
                    $tone = 'pill-ok';
                }
                ?>
                <tr>
                    <td>#<?= (int)$item['id'] ?></td>
                    <td>
                        <?= e($full !== '' ? $full : ($item['student_name'] ?? '—')) ?><br>
                        <span class="muted"><?= e($item['student_email'] ?? '') ?></span>
                    </td>
                    <td><?= e($item['certification_code'] ?? '') ?> · <?= e($item['certification_name'] ?? '') ?></td>
                    <td>
                        <span class="pill <?= e($tone) ?>"><?= e($item['attention_label'] ?? '') ?></span>
                        <?php if (!empty($item['current_step_title'])): ?>
                            <br><small class="muted">Paso: <?= e($item['current_step_title']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e($item['attention_hint'] ?? '') ?></td>
                    <td><?= e($item['updated_at'] ?? '') ?></td>
                    <td><a class="btn btn-ghost" href="/admin/cases/view?id=<?= (int)$item['id'] ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

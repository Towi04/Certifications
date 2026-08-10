<?php
require __DIR__ . '/../_nav.php';
$providers = $providers ?? [];
$certifications = $certifications ?? [];
$items = $items ?? [];
$counts = $counts ?? ['available' => 0, 'assigned' => 0, 'void' => 0];
$status = $status ?? '';
$providerFilter = $providerFilter ?? null;
$certFilter = $certFilter ?? null;
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Inventario de códigos</h2>
            <p class="muted" style="margin:0.35rem 0 0">
                Sube los Exam ID / contraseñas que te entrega el proveedor (compra previa).
                Al confirmar el pago de un alumno con protocolo de inventario, se asigna un código y se envía la plantilla de acceso.
            </p>
        </div>
    </div>

    <p style="margin-top:1rem">
        Disponibles: <strong><?= (int)($counts['available'] ?? 0) ?></strong>
        · Asignados: <strong><?= (int)($counts['assigned'] ?? 0) ?></strong>
        · Anulados: <strong><?= (int)($counts['void'] ?? 0) ?></strong>
    </p>

    <h3>Cargar lote</h3>
    <form method="post" action="/admin/inventory/import" enctype="multipart/form-data" class="stack form-grid">
        <label>Proveedor
            <select name="provider_id">
                <option value="">— opcional —</option>
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Certificación
            <select name="certification_id">
                <option value="">— opcional (recomendado iTEP) —</option>
                <?php foreach ($certifications as $c): ?>
                    <option value="<?= (int)$c['id'] ?>">
                        <?= e(($c['provider_name'] ?? '') . ' · ' . ($c['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Etiqueta de lote<input name="batch_label" placeholder="Ej. compra abr-2026"></label>
        <label class="field-wide">Pegar códigos (una línea por código)
            <span class="muted" style="font-weight:400;display:block;margin:0.2rem 0 0.4rem">
                Formato: <code>ExamID,Contraseña</code> o separado por tab. También CSV con encabezados exam_id / access_code.
            </span>
            <textarea name="codes_text" rows="8" placeholder="ITEP12345,abcXYZ99&#10;ITEP12346,defUVW88"></textarea>
        </label>
        <label>O subir CSV
            <input type="file" name="codes_file" accept=".csv,.txt,text/csv,text/plain">
        </label>
        <div class="actions"><button class="btn" type="submit">Importar códigos</button></div>
    </form>
</section>

<section class="note">
    <h3>Listado</h3>
    <form method="get" action="/admin/inventory" class="stack form-grid">
        <label>Estatus
            <select name="status" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach (['available' => 'Disponible', 'assigned' => 'Asignado', 'void' => 'Anulado'] as $k => $lab): ?>
                    <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Proveedor
            <select name="provider_id" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)$providerFilter === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Certificación
            <select name="certification_id" onchange="this.form.submit()">
                <option value="">Todas</option>
                <?php foreach ($certifications as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)$certFilter === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Exam ID</th>
                <th>Clave</th>
                <th>Estatus</th>
                <th>Certificación</th>
                <th>Proveedor</th>
                <th>Lote</th>
                <th>Caso</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$items): ?>
                <tr><td colspan="8" class="muted">Sin códigos. Importa el primer lote arriba.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $row): ?>
                <tr>
                    <td><code><?= e($row['exam_id'] ?? '') ?></code></td>
                    <td><code><?= e($row['access_code'] ?? '') ?></code></td>
                    <td><?= e($row['status'] ?? '') ?></td>
                    <td><?= e($row['certification_name'] ?? '—') ?></td>
                    <td><?= e($row['provider_name'] ?? '—') ?></td>
                    <td><?= e($row['batch_label'] ?? '—') ?></td>
                    <td>
                        <?php if (!empty($row['assigned_case_id'])): ?>
                            <a href="/admin/cases/view?id=<?= (int)$row['assigned_case_id'] ?>">#<?= (int)$row['assigned_case_id'] ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (($row['status'] ?? '') === 'available'): ?>
                            <form method="post" action="/admin/inventory/void" class="inline-form"
                                  onsubmit="return confirm('¿Anular este código?');">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="linkish">Anular</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

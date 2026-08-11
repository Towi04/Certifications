<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$assignments = $assignments ?? [];
$statusLabels = [
    'pending' => 'Pendiente de firma',
    'submitted' => 'En revisión',
    'approved' => 'Confirmado',
    'rejected' => 'Rechazado',
    'expired' => 'Plazo vencido',
];
?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <p class="muted">
        Sube aquí la <strong>plantilla PDF</strong> del convenio para un nivel TR.
        Al <strong>publicar</strong>, se asigna a todos los partners de ese nivel, se les notifica por correo
        y se restringe el registro de alumnos hasta que suban la versión firmada y Doceo la confirme.
    </p>
    <form method="post" action="/admin/agreements/save" class="stack form-grid" enctype="multipart/form-data">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
        <label>Nivel TR
            <select name="partner_tier_id" required <?= $item ? 'disabled' : '' ?>>
                <?php foreach ($tiers as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)($item['partner_tier_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($item): ?>
                <input type="hidden" name="partner_tier_id" value="<?= (int)$item['partner_tier_id'] ?>">
                <small class="muted">El nivel no se cambia en una versión ya creada.</small>
            <?php endif; ?>
        </label>
        <label>Nombre de la versión<input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
        <label>Año<input type="number" name="year" required value="<?= e((string)($item['year'] ?? date('Y'))) ?>"></label>
        <label>Válido desde<input type="date" name="valid_from" required value="<?= e($item['valid_from'] ?? date('Y-01-01')) ?>"></label>
        <label>Válido hasta<input type="date" name="valid_to" value="<?= e($item['valid_to'] ?? '') ?>"></label>
        <label>Días para firmar
            <input type="number" min="1" max="365" name="sign_deadline_days" required
                   value="<?= e((string)($item['sign_deadline_days'] ?? 15)) ?>">
            <small class="muted">Plazo desde la publicación (o desde el alta del partner).</small>
        </label>
        <label class="field-wide">PDF plantilla (en blanco)
            <input type="file" name="blank_pdf" accept=".pdf,application/pdf" <?= empty($item['pdf_path']) ? 'required' : '' ?>>
            <?php if (!empty($item['pdf_path'])): ?>
                <small class="muted">
                    Actual:
                    <a href="/media?f=<?= e(rawurlencode($item['pdf_path'])) ?>" target="_blank" rel="noopener">ver PDF</a>
                </small>
            <?php endif; ?>
        </label>
        <label class="field-wide">Notas<textarea name="notes" rows="3"><?= e($item['notes'] ?? '') ?></textarea></label>
        <div class="actions">
            <button class="btn" type="submit">Guardar versión</button>
            <a class="btn btn-ghost" href="/admin/agreements">Volver</a>
        </div>
    </form>
</section>

<?php if ($item): ?>
<section class="note">
    <h3>Publicar a partners del nivel</h3>
    <p class="muted">
        Marca esta versión como vigente, la asigna a <strong>todos</strong> los partners del nivel
        <?= e($item['tier_name'] ?? '') ?>, restringe el registro de alumnos y envía el correo de firma.
        <?php if (!empty($item['published_at'])): ?>
            Última publicación: <?= e($item['published_at']) ?>.
        <?php endif; ?>
        <?php if (!empty($item['is_current'])): ?>
            <span class="pill">Vigente</span>
        <?php endif; ?>
    </p>
    <form method="post" action="/admin/agreements/publish" class="stack form-grid"
          onsubmit="return confirm('¿Publicar esta versión a todos los partners del nivel? Se restringirá el registro de alumnos hasta firmar y confirmar.');">
        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
        <label>Días para firmar (esta publicación)
            <input type="number" min="1" max="365" name="sign_deadline_days"
                   value="<?= e((string)($item['sign_deadline_days'] ?? 15)) ?>">
        </label>
        <div class="actions">
            <button class="btn" type="submit" <?= empty($item['pdf_path']) ? 'disabled' : '' ?>>
                Publicar y notificar
            </button>
        </div>
    </form>
</section>

<section class="note">
    <h3>Firmas de partners</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Partner</th>
                <th>Estado</th>
                <th>Plazo</th>
                <th>PDF firmado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($assignments as $a): ?>
                <?php $st = (string)($a['signature_status'] ?? 'pending'); ?>
                <tr>
                    <td>
                        <?= e($a['partner_name'] ?? '') ?><br>
                        <small class="muted"><?= e($a['email'] ?? '') ?></small>
                    </td>
                    <td><?= e($statusLabels[$st] ?? $st) ?></td>
                    <td><?= e($a['deadline_at'] ?? '—') ?></td>
                    <td>
                        <?php if (!empty($a['signed_path'])): ?>
                            <a href="/media?f=<?= e(rawurlencode($a['signed_path'])) ?>" target="_blank" rel="noopener">ver</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($st === 'submitted'): ?>
                            <form method="post" action="/admin/agreements/approve-signature" style="display:inline">
                                <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
                                <input type="hidden" name="agreement_id" value="<?= (int)$item['id'] ?>">
                                <button class="btn" type="submit">Confirmar y reactivar</button>
                            </form>
                            <form method="post" action="/admin/agreements/reject-signature" class="stack" style="margin-top:.5rem">
                                <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
                                <input type="hidden" name="agreement_id" value="<?= (int)$item['id'] ?>">
                                <input name="reject_reason" required placeholder="Motivo del rechazo" style="min-width:12rem">
                                <button class="btn btn-ghost" type="submit">Rechazar</button>
                            </form>
                        <?php elseif (!empty($a['reject_reason'])): ?>
                            <small class="muted"><?= e($a['reject_reason']) ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$assignments): ?>
                <tr><td colspan="5" class="muted">Aún no hay partners asignados a esta versión. Publícala para asignar el nivel.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="note">
    <h3>Precios TR</h3>
    <p class="muted">
        Los precios por nivel se capturan en cada certificación (Precio público, Costo Doceo y precios por nivel TR).
    </p>
    <p><a class="btn btn-ghost" href="/admin/certifications">Ir a certificaciones</a></p>
</section>
<?php endif; ?>

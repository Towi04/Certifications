<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$isEdit = $item !== null;
$requiresInvoice = !empty($item['requires_invoice']);
?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <p class="muted">
        El usuario Partner TR se crea aquí (no en Usuarios). Contraseña temporal:
        <code><?= e(\App\Users\UserRepository::PARTNER_DEFAULT_PASSWORD) ?></code>
        — deberán cambiarla en el primer acceso.
        El convenio vigente se toma automáticamente del nivel TR.
    </p>

    <form method="post" action="/admin/partners/save" class="stack form-grid" enctype="multipart/form-data">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
        <?php endif; ?>

        <label>Nombre
            <input name="first_name" required value="<?= e($item['first_name'] ?? '') ?>" autocomplete="given-name">
        </label>
        <label>Apellidos
            <input name="last_name" required value="<?= e($item['last_name'] ?? '') ?>" autocomplete="family-name">
        </label>
        <label>Correo
            <input type="email" name="email" required value="<?= e($item['email'] ?? '') ?>" autocomplete="email">
            <?php if ($isEdit && !empty($item['username'])): ?>
                <small class="muted">Usuario login: <code><?= e($item['username']) ?></code></small>
            <?php endif; ?>
        </label>
        <label>Teléfono
            <input name="phone" value="<?= e($item['phone'] ?? ($item['user_phone'] ?? '')) ?>" autocomplete="tel">
        </label>
        <label>Escuela / organización
            <input name="organization" value="<?= e($item['organization'] ?? '') ?>">
        </label>
        <label>Nivel TR
            <select name="partner_tier_id" required>
                <option value="">— Selecciona —</option>
                <?php foreach ($tiers as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)($item['partner_tier_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= e($t['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="muted">Se asigna el convenio vigente de ese nivel.</small>
        </label>

        <fieldset class="field-wide score-ranges-fieldset">
            <legend>Dirección de paquetería</legend>
            <div class="form-grid" style="margin:0">
                <label class="field-wide">Calle y número
                    <input name="shipping_address_line" required value="<?= e($item['shipping_address_line'] ?? '') ?>">
                </label>
                <label class="field-wide">Interior / referencias
                    <input name="shipping_address_line2" value="<?= e($item['shipping_address_line2'] ?? '') ?>">
                </label>
                <label>Colonia
                    <input name="shipping_neighborhood" value="<?= e($item['shipping_neighborhood'] ?? '') ?>">
                </label>
                <label>Ciudad
                    <input name="shipping_city" required value="<?= e($item['shipping_city'] ?? '') ?>">
                </label>
                <label>Estado
                    <input name="shipping_state" value="<?= e($item['shipping_state'] ?? '') ?>">
                </label>
                <label>C.P.
                    <input name="shipping_postal_code" value="<?= e($item['shipping_postal_code'] ?? '') ?>">
                </label>
                <label>País
                    <input name="shipping_country" value="<?= e($item['shipping_country'] ?? 'México') ?>">
                </label>
            </div>
        </fieldset>

        <label class="field-wide">Convenio firmado (PDF)
            <input type="file" name="signed_agreement" accept=".pdf,application/pdf" <?= $isEdit ? '' : 'required' ?>>
            <?php if (!empty($item['signed_agreement_path'])): ?>
                <small class="muted">
                    Actual:
                    <a href="/media?f=<?= e(rawurlencode($item['signed_agreement_path'])) ?>" target="_blank" rel="noopener">ver archivo</a>
                </small>
            <?php endif; ?>
        </label>

        <label class="check field-wide">
            <input type="checkbox" name="requires_invoice" id="requiresInvoice" <?= $requiresInvoice ? 'checked' : '' ?>>
            Requiere factura
        </label>
        <label class="field-wide" id="taxStatusField">
            Constancia de Situación Fiscal (PDF)
            <input type="file" name="tax_status" accept=".pdf,application/pdf">
            <?php if (!empty($item['tax_status_path'])): ?>
                <small class="muted">
                    Actual:
                    <a href="/media?f=<?= e(rawurlencode($item['tax_status_path'])) ?>" target="_blank" rel="noopener">ver archivo</a>
                </small>
            <?php else: ?>
                <small class="muted">Obligatoria si marca “Requiere factura”.</small>
            <?php endif; ?>
        </label>

        <label class="field-wide">Logo de la escuela (opcional)
            <input type="file" name="logo" accept="image/*">
            <?php if (!empty($item['logo_path'])): ?>
                <small class="muted">
                    Actual:
                    <a href="/media?f=<?= e(rawurlencode($item['logo_path'])) ?>" target="_blank" rel="noopener">ver logo</a>
                </small>
            <?php endif; ?>
        </label>

        <label class="field-wide">Notas
            <textarea name="notes" rows="3"><?= e($item['notes'] ?? '') ?></textarea>
        </label>
        <?php if ($isEdit): ?>
            <label>Motivo de cambio de nivel/convenio
                <input name="assignment_reason" placeholder="Renovación, cambio de nivel, etc.">
            </label>
        <?php endif; ?>

        <div class="actions">
            <button class="btn" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Crear partner' ?></button>
            <a class="btn btn-ghost" href="/admin/partners">Volver</a>
        </div>
    </form>
</section>

<?php if (!empty($history)): ?>
<section class="note">
    <h3>Historial de convenios</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Convenio</th><th>Asignado</th><th>Terminado</th><th>Motivo</th><th>Por</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= e($h['tier_name']) ?> · <?= e($h['agreement_name']) ?> (<?= (int)$h['year'] ?>)</td>
                    <td><?= e($h['assigned_at']) ?></td>
                    <td><?= e($h['ended_at'] ?? 'Vigente') ?></td>
                    <td><?= e($h['reason'] ?? '—') ?></td>
                    <td><?= e($h['created_by_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<script>
(() => {
  const box = document.getElementById('requiresInvoice');
  const field = document.getElementById('taxStatusField');
  const sync = () => { if (field) field.style.opacity = box?.checked ? '1' : '0.65'; };
  box?.addEventListener('change', sync);
  sync();
})();
</script>

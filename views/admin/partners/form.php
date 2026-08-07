<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <form method="post" action="/admin/partners/save" class="stack form-grid">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
        <label>Usuario
            <select name="user_id" required>
                <option value="">— Selecciona —</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int)$u['id'] ?>" <?= (int)($item['user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                        <?= e($u['name']) ?> · <?= e($u['email']) ?> (<?= e($u['role']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nivel TR
            <select name="partner_tier_id">
                <option value="">—</option>
                <?php foreach ($tiers as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)($item['partner_tier_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= e($t['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Convenio vigente
            <select name="current_agreement_id">
                <option value="">Usar el marcado como actual del nivel</option>
                <?php foreach ($agreements as $a): ?>
                    <option value="<?= (int)$a['id'] ?>" <?= (int)($item['current_agreement_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>>
                        <?= e($a['tier_name'] ?? '') ?> · <?= e($a['name']) ?> (<?= (int)$a['year'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Organización<input name="organization" value="<?= e($item['organization'] ?? '') ?>"></label>
        <label>Teléfono<input name="phone" value="<?= e($item['phone'] ?? '') ?>"></label>
        <label>Notas<textarea name="notes" rows="3"><?= e($item['notes'] ?? '') ?></textarea></label>
        <label>Motivo de asignación<input name="assignment_reason" placeholder="Renovación 2026, ascenso, etc."></label>
        <div class="actions">
            <button class="btn" type="submit">Guardar</button>
            <a class="btn btn-ghost" href="/admin/partners">Volver</a>
        </div>
    </form>
    <p class="muted">Crea primero el usuario con rol Partner TR en <a href="/admin/users">Usuarios</a>. Luego asígnalo aquí a un nivel y convenio. Al guardar, el rol se mantiene como <code>partner</code>.</p>
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


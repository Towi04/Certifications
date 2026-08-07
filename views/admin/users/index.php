<?php
require __DIR__ . '/../_nav.php';
$roles = $roles ?? \App\Users\UserRepository::manageableRoles();
$roleLabels = $roleLabels ?? \App\Users\UserRepository::allRoleLabels();
$currentUserId = (int) ($currentUserId ?? 0);
$iconEdit = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M13.5 6.5l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconKey = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="8.5" cy="14.5" r="3.5" stroke="currentColor" stroke-width="1.8"/><path d="M11.5 12.5 19 5m0 0h-3.2M19 5v3.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$iconDisable = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 12h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconEnable = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v8M8 12h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconMail = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="5.5" width="17" height="13" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="m4.5 7.5 7.5 6 7.5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Usuarios</h2>
            <p class="muted" style="margin:0.35rem 0 0">
                Personal Doceo y Partners TR. Los usuarios no se borran: solo se deshabilitan.
            </p>
        </div>
        <div class="actions">
            <a class="btn" href="/admin/users/create">Nuevo usuario</a>
        </div>
    </div>

    <form method="get" class="filters stack form-grid" style="margin-top:1rem">
        <label>Buscar
            <input name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="nombre, correo, usuario, teléfono">
        </label>
        <label>Rol
            <select name="role">
                <option value="">Todos</option>
                <?php foreach ($roleLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['role'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Estado
            <select name="is_active">
                <option value="">Todos</option>
                <option value="1" <?= ($filters['is_active'] ?? '') === '1' ? 'selected' : '' ?>>Activos</option>
                <option value="0" <?= ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Pendientes / deshabilitados</option>
            </select>
        </label>
        <button class="btn" type="submit">Filtrar</button>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php
                $active = (int) $item['is_active'] === 1;
                $pending = !$active && empty($item['email_verified_at']);
                $status = \App\Users\UserRepository::statusLabel($item);
                $display = \App\Users\UserRepository::displayName(
                    $item['first_name'] ?? null,
                    $item['last_name'] ?? null,
                    $item['name'] ?? null
                );
                $roleLabel = $roleLabels[$item['role']] ?? $item['role'];
                ?>
                <tr class="<?= $active ? '' : 'is-row-inactive' ?>">
                    <td><?= e($display) ?></td>
                    <td><code><?= e($item['username'] ?? '—') ?></code></td>
                    <td><?= e($item['email']) ?></td>
                    <td><?= e($item['phone'] ?? '—') ?></td>
                    <td><?= e((string) $roleLabel) ?></td>
                    <td>
                        <?php if ($active): ?>
                            <span class="pill pill-ok">Activo</span>
                        <?php elseif ($pending): ?>
                            <span class="pill pill-muted">Pendiente</span>
                        <?php else: ?>
                            <span class="pill pill-muted">Deshabilitado</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="icon-actions">
                            <a class="icon-btn" href="/admin/users/edit?id=<?= (int)$item['id'] ?>" title="Editar" aria-label="Editar"><?= $iconEdit ?></a>
                            <form method="post" action="/admin/users/reset-password" class="inline-form"
                                  onsubmit="return confirm(<?= json_encode('¿Restablecer la contraseña de “' . $display . '” a Doceo1234?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <input type="hidden" name="redirect" value="/admin/users">
                                <button type="submit" class="icon-btn" title="Restablecer contraseña" aria-label="Restablecer contraseña"><?= $iconKey ?></button>
                            </form>
                            <?php if ($pending): ?>
                                <form method="post" action="/admin/users/resend-activation" class="inline-form"
                                      onsubmit="return confirm(<?= json_encode('¿Reenviar correo de activación a “' . $display . '”?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" class="icon-btn" title="Reenviar activación" aria-label="Reenviar activación"><?= $iconMail ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if ((int)$item['id'] !== $currentUserId): ?>
                                <form method="post" action="/admin/users/toggle-active" class="inline-form"
                                      onsubmit="return confirm(<?= json_encode(($active ? '¿Deshabilitar' : '¿Habilitar') . ' a “' . $display . '”?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                    <input type="hidden" name="redirect" value="/admin/users">
                                    <button type="submit" class="icon-btn" title="<?= $active ? 'Deshabilitar' : 'Habilitar' ?>" aria-label="<?= $active ? 'Deshabilitar' : 'Habilitar' ?>">
                                        <?= $active ? $iconDisable : $iconEnable ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <tr><td colspan="7" class="muted">No hay usuarios con esos filtros.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

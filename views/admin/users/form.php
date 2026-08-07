<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$roles = $roles ?? \App\Users\UserRepository::manageableRoles();
if ($item && ($item['role'] ?? '') === 'student' && !isset($roles['student'])) {
    $roles = ['student' => 'Alumno'] + $roles;
}
$isEdit = $item !== null;
$currentUserId = (int) ($currentUserId ?? 0);
$isSelf = $isEdit && (int) $item['id'] === $currentUserId;
?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <p class="muted">
        Alta de personal (Administrador, Asistente, Gestor) y Partners TR.
        Los permisos por sección se definirán más adelante; por ahora todos los roles de personal entran al panel.
    </p>

    <form method="post" action="/admin/users/save" class="stack form-grid">
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
        </label>
        <label>Teléfono
            <input name="phone" value="<?= e($item['phone'] ?? '') ?>" placeholder="Ej. 55 1234 5678" autocomplete="tel">
        </label>
        <label>Usuario (login)
            <input name="username" required value="<?= e($item['username'] ?? '') ?>" autocomplete="username">
            <small class="muted">Puede iniciar sesión con usuario o correo.</small>
        </label>
        <label>Rol
            <select name="role" required <?= $isSelf ? 'disabled' : '' ?>>
                <?php foreach ($roles as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($item['role'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($isSelf): ?>
                <input type="hidden" name="role" value="<?= e($item['role']) ?>">
                <small class="muted">No puedes cambiar tu propio rol desde aquí.</small>
            <?php endif; ?>
        </label>

        <?php if (!$isEdit): ?>
            <label>Contraseña
                <input type="password" name="password" required autocomplete="new-password">
            </label>
        <?php endif; ?>

        <div class="actions">
            <button class="btn" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Crear usuario' ?></button>
            <a class="btn btn-ghost" href="/admin/users">Volver</a>
            <?php if ($isEdit && !$isSelf): ?>
                <?php $active = (int)($item['is_active'] ?? 0) === 1; ?>
                <button
                    class="btn btn-ghost"
                    type="submit"
                    form="toggleActiveForm"
                    onclick="return confirm(<?= json_encode(($active ? '¿Deshabilitar' : '¿Habilitar') . ' este usuario?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);"
                ><?= $active ? 'Deshabilitar' : 'Habilitar' ?></button>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($isEdit && !$isSelf): ?>
        <form id="toggleActiveForm" method="post" action="/admin/users/toggle-active" class="hidden-form">
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <input type="hidden" name="redirect" value="/admin/users/edit?id=<?= (int)$item['id'] ?>">
        </form>
    <?php endif; ?>
</section>

<?php if ($isEdit): ?>
<section class="note">
    <h3>Restablecer contraseña</h3>
    <p class="muted">Define una contraseña nueva para este usuario. No se envía por correo automáticamente.</p>
    <form method="post" action="/admin/users/reset-password" class="stack form-grid">
        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
        <label>Nueva contraseña
            <input type="password" name="password" required autocomplete="new-password">
        </label>
        <label>Confirmar contraseña
            <input type="password" name="password_confirm" required autocomplete="new-password">
        </label>
        <div class="actions">
            <button class="btn" type="submit">Restablecer contraseña</button>
        </div>
    </form>
</section>
<?php endif; ?>

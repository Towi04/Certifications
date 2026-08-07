<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$roles = $roles ?? \App\Users\UserRepository::manageableRoles();
$lockedRole = null;
if ($item && in_array(($item['role'] ?? ''), ['partner', 'student'], true)) {
    $lockedRole = $item['role'];
    $labels = \App\Users\UserRepository::allRoleLabels();
    $roles = [$lockedRole => $labels[$lockedRole] ?? $lockedRole] + $roles;
}
$isEdit = $item !== null;
$currentUserId = (int) ($currentUserId ?? 0);
$isSelf = $isEdit && (int) $item['id'] === $currentUserId;
$defaultPassword = \App\Users\UserRepository::DEFAULT_PASSWORD;
?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <p class="muted">
        Alta de personal Doceo (Administrador, Asistente, Gestor).
        Los Partners TR se dan de alta en <a href="/admin/partners">Partners TR</a>.
        La contraseña temporal es <code><?= e($defaultPassword) ?></code>; al guardar se envía un correo
        con usuario, contraseña y enlace para activar la cuenta y elegir una nueva.
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
            <select name="role" required <?= ($isSelf || $lockedRole) ? 'disabled' : '' ?>>
                <?php foreach ($roles as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($item['role'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($isSelf || $lockedRole): ?>
                <input type="hidden" name="role" value="<?= e($item['role']) ?>">
                <small class="muted">
                    <?= $lockedRole ? 'Los Partners TR / alumnos se gestionan en su ficha correspondiente.' : 'No puedes cambiar tu propio rol desde aquí.' ?>
                </small>
            <?php endif; ?>
        </label>

        <?php if (!$isEdit): ?>
            <p class="field-wide muted">
                Contraseña temporal asignada automáticamente:
                <code><?= e($defaultPassword) ?></code>
            </p>
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

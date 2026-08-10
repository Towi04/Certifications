<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$tokens = $tokens ?? [];
$audience = (string)($item['audience'] ?? 'student');
$toMode = (string)($item['to_mode'] ?? 'student');
$ccMode = (string)($item['cc_mode'] ?? 'none');
?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <form method="post" action="/admin/mail-templates/save" class="stack form-grid">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
        <label>Código
            <input name="code" required pattern="[A-Za-z0-9_]+" value="<?= e($item['code'] ?? '') ?>"
                   <?= $item ? 'readonly' : '' ?> placeholder="uks_solicitud">
            <small class="muted">Identificador único (snake_case). No se cambia al editar.</small>
        </label>
        <label>Nombre<input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
        <label>Audiencia
            <select name="audience">
                <?php foreach (['student' => 'Alumno', 'provider' => 'Proveedor', 'internal' => 'Interno', 'other' => 'Otro'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $audience === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Destinatario (To)
            <select name="to_mode" id="toMode">
                <?php foreach (['student' => 'Alumno del caso', 'provider' => 'Contacto del proveedor', 'fixed' => 'Correo fijo', 'manual' => 'Manual (no auto)'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $toMode === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label id="toFixedField">Correo fijo (To)
            <input type="email" name="to_fixed" value="<?= e($item['to_fixed'] ?? '') ?>"
                   placeholder="pruebas@tudominio.com">
            <small class="muted">En pruebas de proveedor, pon aquí tu correo en lugar del real.</small>
        </label>
        <label>Copia (CC)
            <select name="cc_mode">
                <?php foreach (['none' => 'Ninguna', 'tr' => 'Partner TR', 'fixed' => 'Correo fijo', 'case_cc' => 'CC del caso'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $ccMode === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>CC fijo<input type="email" name="cc_fixed" value="<?= e($item['cc_fixed'] ?? '') ?>"></label>
        <label class="field-wide">Asunto<input name="subject" required value="<?= e($item['subject'] ?? '') ?>"></label>
        <label class="field-wide">Cuerpo HTML
            <textarea name="body_html" rows="14" class="html-editor" required><?= e($item['body_html'] ?? '') ?></textarea>
        </label>
        <label class="check"><input type="checkbox" name="attach_export" <?= !empty($item['attach_export']) ? 'checked' : '' ?>> Adjuntar exportación del proveedor</label>
        <label class="check"><input type="checkbox" name="is_active" <?= !isset($item) || !empty($item['is_active']) ? 'checked' : '' ?>> Activa</label>
        <div class="actions">
            <button class="btn" type="submit">Guardar</button>
            <a class="btn btn-ghost" href="/admin/mail-templates">Volver</a>
            <?php if ($item): ?>
                <button class="btn btn-ghost" type="submit" formaction="/admin/mail-templates/delete"
                        formmethod="post"
                        onclick="return confirm('¿Eliminar esta plantilla?');">Eliminar</button>
            <?php endif; ?>
        </div>
    </form>
</section>
<section class="note">
    <h3>Tokens disponibles</h3>
    <p class="muted">Úsalos como <code>{{Nombre}}</code> o <code>&lt;&lt;Nombre&gt;&gt;</code> en asunto y cuerpo.</p>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Token</th><th>Significado</th></tr></thead>
            <tbody>
            <?php foreach ($tokens as $key => $help): ?>
                <tr>
                    <td><code>{{<?= e($key) ?>}}</code></td>
                    <td><?= e($help) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
(() => {
  const mode = document.getElementById('toMode');
  const field = document.getElementById('toFixedField');
  const sync = () => {
    if (!field || !mode) return;
    field.style.display = (mode.value === 'fixed' || mode.value === 'provider') ? '' : 'none';
  };
  mode?.addEventListener('change', sync);
  sync();
})();
</script>

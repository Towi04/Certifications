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
    <p class="muted">
        Las plantillas de <strong>solicitud al proveedor</strong> (p. ej. <code>uks_solicitud</code>) se activan
        desde <strong>Admin → Protocolos → “Plantilla solicitud a empresa”</strong>.
        Luego, en el <strong>caso</strong>, usas “Confirmar pago y solicitar” o “Enviar solicitud al proveedor”
        (ahí subes el comprobante).
    </p>
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
        <div class="field-wide html-field" data-html-field>
            <div class="html-field-head">
                <span class="html-field-title">Cuerpo HTML</span>
                <button type="button" class="icon-btn html-preview-toggle" title="Vista previa / código"
                        aria-label="Alternar vista previa HTML" aria-pressed="false">&lt;/&gt;</button>
            </div>
            <textarea name="body_html" rows="14" class="html-editor" required><?= e($item['body_html'] ?? '') ?></textarea>
            <div class="html-preview prose" hidden></div>
            <small class="muted">Pulsa <code>&lt;/&gt;</code> para ver el correo renderizado (sin etiquetas HTML).</small>
        </div>
        <label class="check field-wide">
            <input type="checkbox" name="attach_export" <?= !empty($item['attach_export']) ? 'checked' : '' ?>>
            Incluir link de exportación del proveedor en el correo
        </label>
        <p class="muted field-wide" style="margin-top:-0.5rem">
            Ya <strong>no se adjunta</strong> el CSV/Excel (rompe la entrega en el hosting).
            Se agrega el enlace público <code>{{Exportacion URL}}</code> / botón
            <code>{{Exportacion Boton}}</code>. El comprobante usa
            <code>{{Comprobante URL}}</code> / <code>{{Comprobante Boton}}</code>.
            Actívalo en plantillas de solicitud (<code>uks_solicitud</code>, <code>toefl_solicitud</code>, <code>reagenda_solicitud</code>).
        </p>
        <label class="check field-wide">
            <input type="checkbox" name="attach_regulation" <?= !empty($item['attach_regulation']) ? 'checked' : '' ?>>
            Adjuntar reglamento del alumno (PDF firmado; si no hay, el original)
        </label>
        <p class="muted field-wide" style="margin-top:-0.5rem">
            Preferible para solicitudes al proveedor. Si solo quieres un botón con link en el cuerpo del correo
            (como antes), usa el token <code>{{Reglamento Boton}}</code> o
            <code>{{Reglamento Firmado Boton}}</code> / <code>{{Reglamento Firmado URL}}</code>
            sin marcar este check.
        </p>
        <label class="check"><input type="checkbox" name="is_active" <?= !isset($item) || !empty($item['is_active']) ? 'checked' : '' ?>> Activa</label>
        <div class="actions">
            <button class="btn" type="submit">Guardar</button>
            <a class="btn btn-ghost" href="/admin/mail-templates">Volver</a>
            <a class="btn btn-ghost" href="/admin/protocols">Ir a Protocolos</a>
        </div>
    </form>
    <?php if ($item): ?>
        <form method="post" action="/admin/mail-templates/delete" style="margin-top:1rem"
              onsubmit="return confirm('¿Eliminar esta plantilla?');">
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <button class="btn btn-ghost" type="submit">Eliminar plantilla</button>
        </form>
    <?php endif; ?>
</section>
<section class="note">
    <h3>Tokens disponibles</h3>
    <p class="muted">Úsalos como <code>{{Nombre}}</code> o <code>&lt;&lt;Nombre&gt;&gt;</code> en asunto y cuerpo. En reagendas, <code>{{Fecha}}</code>/<code>{{Hora}}</code> usan la nueva fecha si existe.</p>
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

  document.querySelectorAll('[data-html-field]').forEach((wrap) => {
    const btn = wrap.querySelector('.html-preview-toggle');
    const ta = wrap.querySelector('textarea.html-editor');
    const preview = wrap.querySelector('.html-preview');
    if (!btn || !ta || !preview) return;
    btn.addEventListener('click', () => {
      const showing = btn.getAttribute('aria-pressed') === 'true';
      if (showing) {
        preview.hidden = true;
        preview.innerHTML = '';
        ta.hidden = false;
        btn.setAttribute('aria-pressed', 'false');
        btn.classList.remove('is-active');
        btn.title = 'Vista previa';
      } else {
        preview.innerHTML = ta.value.trim() !== '' ? ta.value : '<p class="muted"><em>(Vacío)</em></p>';
        ta.hidden = true;
        preview.hidden = false;
        btn.setAttribute('aria-pressed', 'true');
        btn.classList.add('is-active');
        btn.title = 'Ver código HTML';
      }
    });
  });
})();
</script>

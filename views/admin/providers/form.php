<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$agreements = $agreements ?? [];
$certifications = $certifications ?? [];
$authType = $item['auth_proof_type'] ?? 'none';
?>

<section class="provider-form-wrap">
    <form method="post" action="/admin/providers/save" enctype="multipart/form-data" class="provider-card">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>

        <div class="provider-card-head">
            <div>
                <h2><?= e($title) ?></h2>
                <p class="muted">Datos del proveedor, contacto y prueba de autorización</p>
            </div>
            <?php if (!empty($item['logo_path'])): ?>
                <img class="provider-logo-preview" src="/media?f=<?= e(rawurlencode($item['logo_path'])) ?>" alt="Logo">
            <?php endif; ?>
        </div>

        <div class="form-grid">
            <label>Código<input name="code" required value="<?= e($item['code'] ?? '') ?>" placeholder="CERTIPORT"></label>
            <label>Nombre<input name="name" required value="<?= e($item['name'] ?? '') ?>" placeholder="Certiport"></label>
            <label>Sitio web<input name="website_url" type="url" value="<?= e($item['website_url'] ?? '') ?>" placeholder="https://"></label>
            <label>Logotipo
                <input type="file" name="logo" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,image/*">
                <small class="muted">PNG/JPG/SVG. Reemplaza el actual si subes uno nuevo.</small>
            </label>
        </div>

        <h3 class="section-title">Contacto comercial</h3>
        <div class="form-grid">
            <label>Nombre del contacto<input name="contact_name" value="<?= e($item['contact_name'] ?? '') ?>"></label>
            <label>Correo<input type="email" name="contact_email" value="<?= e($item['contact_email'] ?? '') ?>"></label>
            <label>Teléfono<input name="contact_phone" value="<?= e($item['contact_phone'] ?? '') ?>"></label>
            <label>WhatsApp<input name="contact_whatsapp" value="<?= e($item['contact_whatsapp'] ?? '') ?>" placeholder="+52…"></label>
        </div>

        <h3 class="section-title">Distribuidor autorizado (opcional)</h3>
        <p class="muted section-hint">
            Algunos publican tu enlace como distribuidor; otros dan un certificado PDF; otros no tienen comprobante.
        </p>
        <div class="auth-options">
            <label class="auth-option">
                <input type="radio" name="auth_proof_type" value="none" <?= $authType === 'none' ? 'checked' : '' ?>>
                <span>Sin comprobante</span>
            </label>
            <label class="auth-option">
                <input type="radio" name="auth_proof_type" value="url" <?= $authType === 'url' ? 'checked' : '' ?>>
                <span>Enlace en su página</span>
            </label>
            <label class="auth-option">
                <input type="radio" name="auth_proof_type" value="document" <?= $authType === 'document' ? 'checked' : '' ?>>
                <span>Documento / certificado</span>
            </label>
        </div>
        <div class="form-grid auth-fields">
            <label class="auth-url-field">Enlace de verificación
                <input type="url" name="auth_proof_url" value="<?= e($item['auth_proof_url'] ?? '') ?>" placeholder="https://proveedor.com/distribuidores/…">
            </label>
            <label class="auth-doc-field">Documento de autorización
                <input type="file" name="auth_proof_file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
                <?php if (!empty($item['auth_proof_path'])): ?>
                    <small><a href="/media?f=<?= e(rawurlencode($item['auth_proof_path'])) ?>" target="_blank" rel="noopener">Ver documento actual</a></small>
                <?php endif; ?>
            </label>
        </div>

        <h3 class="section-title">Notas internas</h3>
        <div class="form-grid">
            <label>Notas<textarea name="notes" rows="3"><?= e($item['notes'] ?? '') ?></textarea></label>
            <label class="check"><input type="checkbox" name="is_active" <?= !isset($item) || (int)($item['is_active'] ?? 1) ? 'checked' : '' ?>> Proveedor activo</label>
        </div>

        <div class="actions">
            <button class="btn" type="submit">Guardar proveedor</button>
            <a class="btn btn-ghost" href="/admin/providers">Volver al listado</a>
        </div>
    </form>

    <?php if ($item): ?>
        <section class="provider-card" id="agreements">
            <h2>Convenios firmados (PDF)</h2>
            <p class="muted">Puedes subir nuevas versiones cada año. Marca cuál está vigente.</p>

            <?php if ($agreements): ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                        <tr><th>Etiqueta</th><th>Año</th><th>Firmado</th><th>Estado</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($agreements as $a): ?>
                            <tr>
                                <td>
                                    <a href="/media?f=<?= e(rawurlencode($a['file_path'])) ?>" target="_blank" rel="noopener">
                                        <?= e($a['label']) ?>
                                    </a>
                                </td>
                                <td><?= e((string)($a['year'] ?? '—')) ?></td>
                                <td><?= e($a['signed_on'] ?? '—') ?></td>
                                <td><?= (int)$a['is_current'] ? '<span class="pill pill-ok">Vigente</span>' : '—' ?></td>
                                <td class="row-actions">
                                    <?php if (!(int)$a['is_current']): ?>
                                        <form method="post" action="/admin/providers/agreement/current" class="inline-form">
                                            <input type="hidden" name="provider_id" value="<?= (int)$item['id'] ?>">
                                            <input type="hidden" name="agreement_id" value="<?= (int)$a['id'] ?>">
                                            <button type="submit" class="linkish">Marcar vigente</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="/admin/providers/agreement/delete" class="inline-form" onsubmit="return confirm('¿Eliminar este PDF?');">
                                        <input type="hidden" name="provider_id" value="<?= (int)$item['id'] ?>">
                                        <input type="hidden" name="agreement_id" value="<?= (int)$a['id'] ?>">
                                        <button type="submit" class="linkish">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="muted">Aún no hay convenios subidos.</p>
            <?php endif; ?>

            <form method="post" action="/admin/providers/agreement" enctype="multipart/form-data" class="stack form-grid" style="margin-top:1rem">
                <input type="hidden" name="provider_id" value="<?= (int)$item['id'] ?>">
                <label>Etiqueta<input name="label" required placeholder="Convenio 2026"></label>
                <label>Año<input type="number" name="year" value="<?= e(date('Y')) ?>"></label>
                <label>Fecha de firma<input type="date" name="signed_on"></label>
                <label>PDF del convenio<input type="file" name="agreement_file" required accept=".pdf,application/pdf"></label>
                <label>Notas<input name="notes" placeholder="Opcional"></label>
                <label class="check"><input type="checkbox" name="is_current" checked> Marcar como vigente</label>
                <div class="actions"><button class="btn" type="submit">Subir nueva versión</button></div>
            </form>
        </section>

        <section class="provider-card" id="certs">
            <h2>Certificaciones de este proveedor</h2>
            <p class="muted">Solo el nombre por ahora. El detalle se completa después en Certificaciones.</p>

            <?php if ($certifications): ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Nombre</th><th>Código</th><th>Publicada</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($certifications as $c): ?>
                            <tr>
                                <td><?= e($c['name']) ?></td>
                                <td><code><?= e($c['code']) ?></code></td>
                                <td><?= (int)$c['is_published'] ? 'Sí' : 'No' ?></td>
                                <td><a href="/admin/certifications/edit?id=<?= (int)$c['id'] ?>">Editar ficha</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="muted">Todavía no hay certificaciones ligadas.</p>
            <?php endif; ?>

            <form method="post" action="/admin/providers/certification" class="stack form-grid" style="margin-top:1rem">
                <input type="hidden" name="provider_id" value="<?= (int)$item['id'] ?>">
                <label>Agregar certificación
                    <input name="name" required placeholder="Ej. TOEFL ITP, IC3 GS6 Digital Literacy…">
                </label>
                <div class="actions"><button class="btn" type="submit">Agregar</button></div>
            </form>
        </section>
    <?php else: ?>
        <p class="muted note">Guarda el proveedor primero para poder subir convenios PDF y agregar certificaciones.</p>
    <?php endif; ?>
</section>

<script>
(() => {
  const syncAuth = () => {
    const type = document.querySelector('input[name="auth_proof_type"]:checked')?.value || 'none';
    document.querySelectorAll('.auth-url-field').forEach((el) => {
      el.style.display = type === 'url' ? '' : 'none';
    });
    document.querySelectorAll('.auth-doc-field').forEach((el) => {
      el.style.display = type === 'document' ? '' : 'none';
    });
  };
  document.querySelectorAll('input[name="auth_proof_type"]').forEach((el) => el.addEventListener('change', syncAuth));
  syncAuth();
})();
</script>

<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$tab = $tab ?? 'proveedor';
$agreements = $agreements ?? [];
$certifications = $certifications ?? [];
$contacts = $contacts ?? [];
$venues = $venues ?? [];
$notes = $notes ?? [];
$authType = $item['auth_proof_type'] ?? 'none';
$icon = $item['logo_icon_path'] ?? $item['logo_path'] ?? null;
$fullLogo = $item['logo_full_path'] ?? null;
$roles = [
    'ventas' => 'Ventas',
    'soporte' => 'Soporte técnico',
    'finanzas' => 'Finanzas',
    'general' => 'General',
    'otro' => 'Otro',
];
$id = $item ? (int) $item['id'] : 0;
$tabs = $item ? [
    'proveedor' => 'Proveedor',
    'contactos' => 'Contactos',
    'sedes' => 'Sedes',
    'autorizacion' => 'Autorización',
    'convenio' => 'Convenio',
    'certificaciones' => 'Certificaciones',
    'notas' => 'Notas',
] : ['proveedor' => 'Proveedor'];
?>

<section class="provider-edit">
    <header class="provider-edit-head">
        <div class="provider-edit-identity">
            <?php if ($icon): ?>
                <img class="provider-edit-logo" src="/media?f=<?= e(rawurlencode($icon)) ?>" alt="" width="56" height="56" style="width:56px;height:56px;object-fit:contain">
            <?php else: ?>
                <span class="provider-edit-fallback"><?= e(mb_substr($item['name'] ?? 'P', 0, 1)) ?></span>
            <?php endif; ?>
            <div>
                <h2><?= e($item['name'] ?? 'Nuevo proveedor') ?></h2>
                <?php if ($item): ?><p class="muted"><code><?= e($item['code']) ?></code></p><?php endif; ?>
            </div>
        </div>
        <a class="btn btn-ghost" href="/admin/providers">Volver al listado</a>
    </header>

    <nav class="provider-tabs" aria-label="Secciones del proveedor">
        <?php foreach ($tabs as $key => $label): ?>
            <?php if ($item): ?>
                <a class="provider-tab <?= $tab === $key ? 'is-active' : '' ?>" href="/admin/providers/edit?id=<?= $id ?>&tab=<?= e($key) ?>"><?= e($label) ?></a>
            <?php else: ?>
                <span class="provider-tab is-active"><?= e($label) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="provider-panels">
        <?php if ($tab === 'proveedor'): ?>
            <section class="provider-panel">
                <h3><?= $item ? 'Datos del proveedor' : 'Nuevo proveedor' ?></h3>
                <form method="post" action="/admin/providers/save" enctype="multipart/form-data" class="form-grid">
                    <?php if ($item): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
                    <input type="hidden" name="tab" value="proveedor">
                    <label>Código<input name="code" required value="<?= e($item['code'] ?? '') ?>"></label>
                    <label>Nombre<input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
                    <label>Sitio web<input type="url" name="website_url" value="<?= e($item['website_url'] ?? '') ?>" placeholder="https://"></label>
                    <label>Logo icono / escudo
                        <input type="file" name="logo_icon" accept="image/*">
                        <small class="muted">Se redimensiona automáticamente (máx. 320×320).</small>
                        <?php if ($icon): ?>
                            <img class="logo-mini" src="/media?f=<?= e(rawurlencode($icon)) ?>" alt="" width="56" height="56" style="width:56px;height:56px;object-fit:contain">
                        <?php endif; ?>
                    </label>
                    <label>Logo completo (con nombre)
                        <input type="file" name="logo_full" accept="image/*">
                        <small class="muted">Para correos (máx. 900×400).</small>
                        <?php if ($fullLogo): ?>
                            <img class="logo-mini logo-mini-wide" src="/media?f=<?= e(rawurlencode($fullLogo)) ?>" alt="" width="140" height="56" style="width:140px;height:56px;object-fit:contain">
                        <?php endif; ?>
                    </label>
                    <div class="actions"><button class="btn" type="submit">Guardar</button></div>
                </form>
                <?php if (!$item): ?>
                    <p class="muted">Después de guardar podrás agregar contactos, sedes, convenio y certificaciones.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($item && $tab === 'contactos'): ?>
            <section class="provider-panel">
                <h3>Contactos</h3>
                <p class="muted">Varios contactos: ventas, soporte, finanzas, etc.</p>
                <?php if ($contacts): ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Rol</th><th>Nombre</th><th>Correo</th><th>Tel / WA</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($contacts as $c): ?>
                                <tr>
                                    <td><?= e($roles[$c['role']] ?? $c['role']) ?><?= (int)$c['is_primary'] ? ' · principal' : '' ?></td>
                                    <td><?= e($c['name']) ?></td>
                                    <td><?= e($c['email'] ?? '—') ?></td>
                                    <td><?= e($c['phone'] ?? '—') ?><?= !empty($c['whatsapp']) ? ' / WA ' . e($c['whatsapp']) : '' ?></td>
                                    <td>
                                        <form method="post" action="/admin/providers/contact/delete" class="inline-form" onsubmit="return confirm('¿Eliminar contacto?');">
                                            <input type="hidden" name="provider_id" value="<?= $id ?>">
                                            <input type="hidden" name="contact_id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="linkish">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="muted">Sin contactos aún.</p>
                <?php endif; ?>

                <form method="post" action="/admin/providers/contact" class="form-grid" style="margin-top:1rem">
                    <input type="hidden" name="provider_id" value="<?= $id ?>">
                    <label>Rol
                        <select name="role" id="contactRole">
                            <?php foreach ($roles as $k => $label): ?>
                                <option value="<?= e($k) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label id="roleCustomField" style="display:none">Nombre del rol
                        <input name="role_custom" placeholder="Ej. Logística, Académico…">
                    </label>
                    <label>Nombre<input name="name" required></label>
                    <label>Correo<input type="email" name="email"></label>
                    <label>Teléfono<input name="phone"></label>
                    <label>WhatsApp<input name="whatsapp"></label>
                    <label>Nota corta<input name="notes" placeholder="Opcional"></label>
                    <label class="check"><input type="checkbox" name="is_primary"> Contacto principal</label>
                    <div class="actions"><button class="btn" type="submit">Agregar contacto</button></div>
                </form>
            </section>
            <script>
            (() => {
              const sel = document.getElementById('contactRole');
              const custom = document.getElementById('roleCustomField');
              const sync = () => { custom.style.display = sel.value === 'otro' ? '' : 'none'; };
              sel.addEventListener('change', sync);
              sync();
            })();
            </script>
        <?php endif; ?>

        <?php if ($item && $tab === 'sedes'): ?>
            <?php
            $editVenue = $editVenue ?? null;
            $editType = $editVenue['venue_type'] ?? 'fixed';
            ?>
            <section class="provider-panel">
                <h3>Sedes y subcentros</h3>
                <p class="muted">
                    <strong>Sede fija:</strong> lugar conocido con dirección (ej. 2 sedes CDMX).<br>
                    <strong>Subcentro:</strong> solo ciudad/estado; el lugar exacto se define al agendar cada aplicación
                    (el alumno lo recibirá en el registro — pendiente).
                </p>
                <?php if ($venues): ?>
                    <div class="venue-cards">
                        <?php foreach ($venues as $v): ?>
                            <?php
                            $vActive = (int)($v['is_active'] ?? 1) === 1;
                            $isSub = ($v['venue_type'] ?? 'fixed') === 'subcentro';
                            ?>
                            <article class="venue-card <?= $vActive ? '' : 'is-inactive' ?> <?= $isSub ? 'is-subcentro' : 'is-fixed' ?>">
                                <header>
                                    <div>
                                        <span class="venue-type-pill"><?= $isSub ? 'Subcentro' : 'Sede fija' ?></span>
                                        <strong><?= e(trim($v['city'] . ($v['state'] ? ', ' . $v['state'] : ''))) ?></strong>
                                        <?php if (!$isSub): ?>
                                            <p class="muted venue-place"><?= e($v['name']) ?></p>
                                        <?php elseif (!empty($v['name'])): ?>
                                            <p class="muted venue-place"><?= e($v['name']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="venue-card-actions">
                                        <a class="linkish" href="/admin/providers/edit?id=<?= $id ?>&tab=sedes&edit_venue=<?= (int)$v['id'] ?>">Editar</a>
                                        <form method="post" action="/admin/providers/venue/toggle-active" class="inline-form"
                                              onsubmit="return confirm(<?= json_encode('¿Seguro que quieres ' . ($vActive ? 'desactivar' : 'activar') . ' ' . ($isSub ? 'el subcentro' : 'la sede') . ' de ' . $v['city'] . '?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);">
                                            <input type="hidden" name="provider_id" value="<?= $id ?>">
                                            <input type="hidden" name="venue_id" value="<?= (int)$v['id'] ?>">
                                            <button type="submit" class="eye-btn" title="<?= $vActive ? 'Desactivar' : 'Activar' ?>" aria-label="<?= $vActive ? 'Desactivar' : 'Activar' ?>">
                                                <?php if ($vActive): ?>
                                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.8"/></svg>
                                                <?php else: ?>
                                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 3l18 18M10.5 10.6A3.2 3.2 0 0 0 13.4 13.5M9.9 5.2C10.6 5.1 11.3 5 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-4.2 4.8M6.1 6.1A17.4 17.4 0 0 0 2 12s3.5 7 10 7c1.3 0 2.5-.3 3.6-.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                                <?php endif; ?>
                                            </button>
                                        </form>
                                        <form method="post" action="/admin/providers/venue/delete" class="inline-form" onsubmit="return confirm('¿Eliminar de forma permanente?');">
                                            <input type="hidden" name="provider_id" value="<?= $id ?>">
                                            <input type="hidden" name="venue_id" value="<?= (int)$v['id'] ?>">
                                            <button type="submit" class="linkish">Eliminar</button>
                                        </form>
                                    </div>
                                </header>
                                <?php if ($isSub): ?>
                                    <p class="venue-pending">Dirección por definir al agendar la aplicación</p>
                                <?php else: ?>
                                    <p>
                                        <?= e($v['address_line'] ?? '') ?>
                                        <?php if (!empty($v['address_line2'])): ?><br><?= e($v['address_line2']) ?><?php endif; ?>
                                        <?php if (!empty($v['neighborhood'])): ?><br><?= e($v['neighborhood']) ?><?php endif; ?>
                                        <?php if (!empty($v['postal_code'])): ?> · CP <?= e($v['postal_code']) ?><?php endif; ?>
                                        <br><?= e($v['country'] ?? 'México') ?>
                                    </p>
                                <?php endif; ?>
                                <p class="muted">
                                    Contacto: <?= e($v['contact_name'] ?? '—') ?>
                                    · <?= e($v['contact_phone'] ?? '—') ?>
                                    <?php if (!empty($v['contact_email'])): ?> · <?= e($v['contact_email']) ?><?php endif; ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">Sin sedes ni subcentros. Ejemplo Cambridge: 2 sedes fijas en CDMX + subcentros por estado.</p>
                <?php endif; ?>

                <h4 class="venue-form-title"><?= $editVenue ? 'Editar' : 'Agregar' ?> sede / subcentro</h4>
                <?php if ($editVenue): ?>
                    <p class="muted"><a href="/admin/providers/edit?id=<?= $id ?>&tab=sedes">Cancelar edición</a></p>
                <?php endif; ?>
                <form method="post" action="/admin/providers/venue" class="form-grid" style="margin-top:0.5rem" id="venueForm">
                    <input type="hidden" name="provider_id" value="<?= $id ?>">
                    <?php if ($editVenue): ?><input type="hidden" name="venue_id" value="<?= (int)$editVenue['id'] ?>"><?php endif; ?>
                    <label>Tipo
                        <select name="venue_type" id="venueType">
                            <option value="fixed" <?= $editType === 'fixed' ? 'selected' : '' ?>>Sede fija (con dirección)</option>
                            <option value="subcentro" <?= $editType === 'subcentro' ? 'selected' : '' ?>>Subcentro (ciudad/estado)</option>
                        </select>
                    </label>
                    <label class="venue-fixed-only">Lugar (universidad / escuela)
                        <input name="name" id="venueName" value="<?= e($editVenue['name'] ?? '') ?>" placeholder="Ej. Universidad X, Campus Norte">
                    </label>
                    <label class="venue-sub-only" style="display:none">Etiqueta del subcentro (opcional)
                        <input name="name_sub" id="venueNameSub" value="<?= e(($editType === 'subcentro') ? ($editVenue['name'] ?? '') : '') ?>" placeholder="Se usa “Subcentro {estado}” si lo dejas vacío">
                    </label>
                    <label>Estado<input name="state" id="venueState" value="<?= e($editVenue['state'] ?? '') ?>" placeholder="Obligatorio en subcentro"></label>
                    <label>Ciudad<input name="city" id="venueCity" required value="<?= e($editVenue['city'] ?? '') ?>"></label>
                    <label class="venue-fixed-only">Calle y número<input name="address_line" id="venueAddress" value="<?= e($editVenue['address_line'] ?? '') ?>"></label>
                    <label class="venue-fixed-only">Interior / referencia<input name="address_line2" value="<?= e($editVenue['address_line2'] ?? '') ?>"></label>
                    <label class="venue-fixed-only">Colonia<input name="neighborhood" value="<?= e($editVenue['neighborhood'] ?? '') ?>"></label>
                    <label class="venue-fixed-only">C.P.<input name="postal_code" value="<?= e($editVenue['postal_code'] ?? '') ?>"></label>
                    <label>País<input name="country" value="<?= e($editVenue['country'] ?? 'México') ?>"></label>
                    <label>Contacto<input name="contact_name" value="<?= e($editVenue['contact_name'] ?? '') ?>"></label>
                    <label>Teléfono<input name="contact_phone" value="<?= e($editVenue['contact_phone'] ?? '') ?>"></label>
                    <label>Correo<input type="email" name="contact_email" value="<?= e($editVenue['contact_email'] ?? '') ?>"></label>
                    <label>Notas<textarea name="notes" rows="2" placeholder="Horarios, acceso, etc."><?= e($editVenue['notes'] ?? '') ?></textarea></label>
                    <div class="actions">
                        <button class="btn" type="submit"><?= $editVenue ? 'Guardar cambios' : 'Agregar' ?></button>
                    </div>
                </form>
            </section>
            <script>
            (() => {
              const type = document.getElementById('venueType');
              const nameFixed = document.getElementById('venueName');
              const nameSub = document.getElementById('venueNameSub');
              const address = document.getElementById('venueAddress');
              const state = document.getElementById('venueState');
              const form = document.getElementById('venueForm');
              const sync = () => {
                const sub = type.value === 'subcentro';
                document.querySelectorAll('.venue-fixed-only').forEach((el) => { el.style.display = sub ? 'none' : ''; });
                document.querySelectorAll('.venue-sub-only').forEach((el) => { el.style.display = sub ? '' : 'none'; });
                if (nameFixed) nameFixed.required = !sub;
                if (address) address.required = !sub;
                if (state) state.required = sub;
              };
              type.addEventListener('change', sync);
              form.addEventListener('submit', () => {
                if (type.value === 'subcentro' && nameSub) {
                  nameFixed.value = nameSub.value.trim();
                }
              });
              sync();
            })();
            </script>
        <?php endif; ?>

        <?php if ($item && $tab === 'autorizacion'): ?>
            <section class="provider-panel">
                <h3>Distribuidor autorizado</h3>
                <p class="muted">Opcional: enlace en su web, documento, o sin comprobante.</p>
                <form method="post" action="/admin/providers/save" enctype="multipart/form-data" class="form-grid">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="tab" value="autorizacion">
                    <input type="hidden" name="code" value="<?= e($item['code']) ?>">
                    <input type="hidden" name="name" value="<?= e($item['name']) ?>">
                    <label>Tipo de comprobante
                        <select name="auth_proof_type" id="authProofType">
                            <option value="none" <?= $authType === 'none' ? 'selected' : '' ?>>Sin comprobante</option>
                            <option value="url" <?= $authType === 'url' ? 'selected' : '' ?>>Link</option>
                            <option value="document" <?= $authType === 'document' ? 'selected' : '' ?>>Documento</option>
                        </select>
                    </label>
                    <label id="authUrlField">Enlace
                        <input type="url" name="auth_proof_url" value="<?= e($item['auth_proof_url'] ?? '') ?>" placeholder="https://…">
                    </label>
                    <label id="authDocField">Documento / certificado
                        <input type="file" name="auth_proof_file" accept=".pdf,image/*">
                        <?php if (!empty($item['auth_proof_path'])): ?>
                            <small><a href="/media?f=<?= e(rawurlencode($item['auth_proof_path'])) ?>" target="_blank" rel="noopener">Ver actual</a></small>
                        <?php endif; ?>
                    </label>
                    <div class="actions"><button class="btn" type="submit">Guardar autorización</button></div>
                </form>
            </section>
            <script>
            (() => {
              const sel = document.getElementById('authProofType');
              const url = document.getElementById('authUrlField');
              const doc = document.getElementById('authDocField');
              const sync = () => {
                url.style.display = sel.value === 'url' ? '' : 'none';
                doc.style.display = sel.value === 'document' ? '' : 'none';
              };
              sel.addEventListener('change', sync);
              sync();
            })();
            </script>
        <?php endif; ?>

        <?php if ($item && $tab === 'convenio'): ?>
            <section class="provider-panel">
                <h3>Convenios firmados (PDF)</h3>
                <?php if ($agreements): ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Etiqueta</th><th>Año</th><th>Firmado</th><th>Estado</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($agreements as $a): ?>
                                <tr>
                                    <td><a href="/media?f=<?= e(rawurlencode($a['file_path'])) ?>" target="_blank" rel="noopener"><?= e($a['label']) ?></a></td>
                                    <td><?= e((string)($a['year'] ?? '—')) ?></td>
                                    <td><?= e($a['signed_on'] ?? '—') ?></td>
                                    <td><?= (int)$a['is_current'] ? '<span class="pill pill-ok">Vigente</span>' : '—' ?></td>
                                    <td class="row-actions">
                                        <?php if (!(int)$a['is_current']): ?>
                                            <form method="post" action="/admin/providers/agreement/current" class="inline-form">
                                                <input type="hidden" name="provider_id" value="<?= $id ?>">
                                                <input type="hidden" name="agreement_id" value="<?= (int)$a['id'] ?>">
                                                <button type="submit" class="linkish">Marcar vigente</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="/admin/providers/agreement/delete" class="inline-form" onsubmit="return confirm('¿Eliminar PDF?');">
                                            <input type="hidden" name="provider_id" value="<?= $id ?>">
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
                    <p class="muted">Aún no hay convenios.</p>
                <?php endif; ?>

                <form method="post" action="/admin/providers/agreement" enctype="multipart/form-data" class="form-grid" style="margin-top:1rem">
                    <input type="hidden" name="provider_id" value="<?= $id ?>">
                    <label>Etiqueta<input name="label" required placeholder="Convenio 2026"></label>
                    <label>Año<input type="number" name="year" value="<?= e(date('Y')) ?>"></label>
                    <label>Fecha de firma<input type="date" name="signed_on"></label>
                    <label>PDF<input type="file" name="agreement_file" required accept=".pdf,application/pdf"></label>
                    <label class="check"><input type="checkbox" name="is_current" checked> Marcar como vigente</label>
                    <div class="actions"><button class="btn" type="submit">Subir versión</button></div>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($item && $tab === 'certificaciones'): ?>
            <section class="provider-panel">
                <h3>Certificaciones</h3>
                <p class="muted">Agrega varias a la vez (solo nombre). El detalle se completa en Certificaciones.</p>
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
                <?php endif; ?>

                <form method="post" action="/admin/providers/certifications" style="margin-top:1rem">
                    <input type="hidden" name="provider_id" value="<?= $id ?>">
                    <div class="table-wrap">
                        <table class="data-table" id="bulkCertTable">
                            <thead><tr><th>#</th><th>Nombre de la certificación</th></tr></thead>
                            <tbody>
                            <?php for ($i = 0; $i < 8; $i++): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><input name="names[]" placeholder="Ej. TOEFL ITP / IC3 GS6…" style="width:100%"></td>
                                </tr>
                            <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="actions" style="margin-top:0.75rem">
                        <button type="button" class="btn btn-ghost" id="addCertRows">+ 5 filas</button>
                        <button class="btn" type="submit">Guardar certificaciones</button>
                    </div>
                </form>
            </section>
            <script>
            (() => {
              const tbody = document.querySelector('#bulkCertTable tbody');
              const btn = document.getElementById('addCertRows');
              btn?.addEventListener('click', () => {
                const start = tbody.querySelectorAll('tr').length;
                for (let i = 1; i <= 5; i++) {
                  const tr = document.createElement('tr');
                  tr.innerHTML = `<td>${start + i}</td><td><input name="names[]" placeholder="Nombre…" style="width:100%"></td>`;
                  tbody.appendChild(tr);
                }
              });
            })();
            </script>
        <?php endif; ?>

        <?php if ($item && $tab === 'notas'): ?>
            <section class="provider-panel">
                <h3>Notas</h3>
                <p class="muted">Bitácora interna: cada nota guarda fecha y quién la escribió.</p>
                <form method="post" action="/admin/providers/note" class="form-grid">
                    <input type="hidden" name="provider_id" value="<?= $id ?>">
                    <label style="grid-column:1/-1">Nueva nota<textarea name="body" rows="3" required placeholder="Escribe la nota…"></textarea></label>
                    <div class="actions"><button class="btn" type="submit">Agregar nota</button></div>
                </form>
                <div class="notes-timeline">
                    <?php foreach ($notes as $n): ?>
                        <article class="note-entry">
                            <header>
                                <strong><?= e($n['author_name'] ?? $n['author_email'] ?? 'Usuario') ?></strong>
                                <time><?= e($n['created_at']) ?></time>
                                <form method="post" action="/admin/providers/note/delete" class="inline-form" onsubmit="return confirm('¿Eliminar nota?');">
                                    <input type="hidden" name="provider_id" value="<?= $id ?>">
                                    <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>">
                                    <button type="submit" class="linkish">Eliminar</button>
                                </form>
                            </header>
                            <p><?= nl2br(e($n['body'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$notes): ?><p class="muted">Sin notas aún.</p><?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</section>

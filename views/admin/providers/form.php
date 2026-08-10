<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$tab = $tab ?? 'proveedor';
$agreements = $agreements ?? [];
$certifications = $certifications ?? [];
$contacts = $contacts ?? [];
$venues = $venues ?? [];
$accounts = $accounts ?? [];
$notes = $notes ?? [];
$editVenue = $editVenue ?? null;
$editContact = $editContact ?? null;
$editAccount = $editAccount ?? null;
$editGroup = $editGroup ?? null;
$editDocument = $editDocument ?? null;
$groups = $groups ?? [];
$provider_documents = $provider_documents ?? [];
$provider_reg_fields = $provider_reg_fields ?? [];
$docTypes = $docTypes ?? [];
$appUrl = $appUrl ?? '';
$showForm = (bool) ($showForm ?? false);
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
    'cuentas' => 'Cuentas',
    'certificaciones' => 'Certificaciones',
    'grupos' => 'Grupos',
    'documentos' => 'Documentos',
    'campos' => 'Campos',
    'notas' => 'Notas',
] : ['proveedor' => 'Proveedor'];

$iconEdit = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M13.5 6.5l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconTrash = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M9 7V5h6v2M8 7l1 12h6l1-12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$iconPhone = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h3l1.5 4-2 1.5a12 12 0 0 0 5.5 5.5L16.5 12.5 20.5 14v3a2 2 0 0 1-2.2 2A15 15 0 0 1 5 7.2 2 2 0 0 1 7 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>';
$iconWa = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5a8 8 0 0 0-6.9 12.1L4.5 20.5l5-1.4A8 8 0 1 0 12 3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.2 9.4c.3-.6.5-.6.8-.6h.6c.2 0 .4.1.5.4l.7 1.7c.1.2 0 .4-.1.6l-.4.5c-.1.1-.1.3 0 .4.4.7 1.1 1.4 1.9 1.9.1.1.3.1.4 0l.5-.4c.2-.1.4-.2.6-.1l1.7.7c.3.1.4.3.4.5v.6c0 .3 0 .5-.6.8-.5.3-1.2.4-1.9.2A8.5 8.5 0 0 1 9 11.3c-.2-.7-.1-1.4.2-1.9Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>';
$iconEye = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.8"/></svg>';
$iconEyeOff = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 3l18 18M10.5 10.6A3.2 3.2 0 0 0 13.4 13.5M9.9 5.2C10.6 5.1 11.3 5 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-4.2 4.8M6.1 6.1A17.4 17.4 0 0 0 2 12s3.5 7 10 7c1.3 0 2.5-.3 3.6-.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconChevron = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$iconCopy = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M4 16V6a2 2 0 0 1 2-2h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
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
                <?php if ($item): ?>
                    <p class="muted">Convenio con: <code><?= e($item['code']) ?></code> <span class="admin-only-hint">(solo admin)</span></p>
                <?php endif; ?>
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
                <p class="muted">
                    Trabajamos como preparation center de un intermediario (<strong>Convenio con</strong>).
                    Alumnos y Teacher Referral solo ven <strong>Certificaciones de</strong> (la marca que ofrecemos).
                </p>
                <form method="post" action="/admin/providers/save" enctype="multipart/form-data" class="form-grid">
                    <?php if ($item): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
                    <input type="hidden" name="tab" value="proveedor">
                    <label>Convenio con
                        <input name="code" required value="<?= e($item['code'] ?? '') ?>" placeholder="Ej. Creative Solutions, ETC Iberoamérica, Lingua Franca">
                        <small class="muted">Partner interno. No se muestra a alumnos ni TR.</small>
                    </label>
                    <label>Certificaciones de
                        <input name="name" required value="<?= e($item['name'] ?? '') ?>" placeholder="Ej. Cambridge, Certiport, TOEFL (IIE)">
                        <small class="muted">Nombre público que ven alumnos y Teacher Referral.</small>
                    </label>
                    <label>Sitio web del convenio
                        <input type="url" name="website_url" value="<?= e($item['website_url'] ?? '') ?>" placeholder="https://creativesolutions.com">
                        <small class="muted">Solo admin (ETC, Creative Solutions, Lingua Franca…).</small>
                    </label>
                    <label>Sitio web de la marca
                        <input type="url" name="brand_website_url" value="<?= e($item['brand_website_url'] ?? '') ?>" placeholder="https://www.cambridge.org">
                        <small class="muted">Público: Cambridge, Certiport, TOEFL/IIE… Visible a alumnos y TR.</small>
                    </label>
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
            <?php
            $knownRoles = array_keys($roles);
            $editRole = $editContact['role'] ?? 'general';
            $editRoleIsCustom = $editContact && !in_array($editRole, $knownRoles, true);
            $contactFormOpen = $showForm || $editContact;
            ?>
            <section class="provider-panel">
                <div class="panel-toolbar">
                    <div>
                        <h3>Contactos</h3>
                        <p class="muted" style="margin:0.25rem 0 0">Ventas, soporte, finanzas, etc.</p>
                    </div>
                    <?php if (!$contactFormOpen): ?>
                        <a class="btn" href="/admin/providers/edit?id=<?= $id ?>&tab=contactos&form=1">Agregar contacto</a>
                    <?php endif; ?>
                </div>

                <?php if ($contactFormOpen): ?>
                    <div class="inline-form-panel">
                        <div class="panel-toolbar">
                            <h4 style="margin:0"><?= $editContact ? 'Editar contacto' : 'Nuevo contacto' ?></h4>
                            <a class="btn btn-ghost" href="/admin/providers/edit?id=<?= $id ?>&tab=contactos">Cancelar</a>
                        </div>
                        <form method="post" action="/admin/providers/contact" class="form-grid" style="margin-top:0.75rem">
                            <input type="hidden" name="provider_id" value="<?= $id ?>">
                            <?php if ($editContact): ?><input type="hidden" name="contact_id" value="<?= (int)$editContact['id'] ?>"><?php endif; ?>
                            <label>Rol
                                <select name="role" id="contactRole">
                                    <?php foreach ($roles as $k => $label): ?>
                                        <option value="<?= e($k) ?>" <?= (!$editRoleIsCustom && $editRole === $k) || ($editRoleIsCustom && $k === 'otro') ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label id="roleCustomField" style="<?= $editRoleIsCustom ? '' : 'display:none' ?>">Nombre del rol
                                <input name="role_custom" value="<?= e($editRoleIsCustom ? $editRole : '') ?>" placeholder="Ej. Logística, Académico…">
                            </label>
                            <label>Nombre<input name="name" required value="<?= e($editContact['name'] ?? '') ?>"></label>
                            <label>Correo<input type="email" name="email" value="<?= e($editContact['email'] ?? '') ?>"></label>
                            <label>Teléfono<input name="phone" value="<?= e($editContact['phone'] ?? '') ?>"></label>
                            <label>WhatsApp<input name="whatsapp" value="<?= e($editContact['whatsapp'] ?? '') ?>"></label>
                            <label>Nota corta<input name="notes" value="<?= e($editContact['notes'] ?? '') ?>" placeholder="Opcional"></label>
                            <label class="check"><input type="checkbox" name="is_primary" <?= !empty($editContact['is_primary']) ? 'checked' : '' ?>> Contacto principal</label>
                            <div class="actions"><button class="btn" type="submit"><?= $editContact ? 'Guardar cambios' : 'Agregar contacto' ?></button></div>
                        </form>
                    </div>
                    <script>
                    (() => {
                      const sel = document.getElementById('contactRole');
                      const custom = document.getElementById('roleCustomField');
                      const sync = () => { custom.style.display = sel.value === 'otro' ? '' : 'none'; };
                      sel.addEventListener('change', sync);
                      sync();
                    })();
                    </script>
                <?php else: ?>
                    <?php if ($contacts): ?>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead><tr><th>Rol</th><th>Nombre</th><th>Correo</th><th>Tel / WA</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($contacts as $c): ?>
                                    <?php
                                    $tel = tel_url($c['phone'] ?? null);
                                    $wa = wa_me_url($c['whatsapp'] ?? ($c['phone'] ?? null));
                                    $hasWa = phone_digits($c['whatsapp'] ?? '') !== '' || phone_digits($c['phone'] ?? '') !== '';
                                    ?>
                                    <tr>
                                        <td><?= e($roles[$c['role']] ?? $c['role']) ?><?= (int)$c['is_primary'] ? ' · principal' : '' ?></td>
                                        <td><?= e($c['name']) ?></td>
                                        <td><?= e($c['email'] ?? '—') ?></td>
                                        <td><?= e($c['phone'] ?? '—') ?><?= !empty($c['whatsapp']) ? ' / WA ' . e($c['whatsapp']) : '' ?></td>
                                        <td>
                                            <div class="icon-actions">
                                                <?php if ($tel): ?>
                                                    <a class="icon-btn" href="<?= e($tel) ?>" title="Llamar" aria-label="Llamar"><?= $iconPhone ?></a>
                                                <?php else: ?>
                                                    <span class="icon-btn is-disabled" title="Sin teléfono" aria-disabled="true"><?= $iconPhone ?></span>
                                                <?php endif; ?>
                                                <?php if ($hasWa && $wa): ?>
                                                    <a class="icon-btn icon-btn-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp"><?= $iconWa ?></a>
                                                <?php else: ?>
                                                    <span class="icon-btn is-disabled" title="Sin WhatsApp" aria-disabled="true"><?= $iconWa ?></span>
                                                <?php endif; ?>
                                                <a class="icon-btn" href="/admin/providers/edit?id=<?= $id ?>&tab=contactos&edit_contact=<?= (int)$c['id'] ?>" title="Editar" aria-label="Editar"><?= $iconEdit ?></a>
                                                <form method="post" action="/admin/providers/contact/delete" class="inline-form" onsubmit="return confirm('¿Eliminar contacto?');">
                                                    <input type="hidden" name="provider_id" value="<?= $id ?>">
                                                    <input type="hidden" name="contact_id" value="<?= (int)$c['id'] ?>">
                                                    <button type="submit" class="icon-btn icon-btn-danger" title="Eliminar" aria-label="Eliminar"><?= $iconTrash ?></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="muted">Sin contactos aún.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($item && $tab === 'sedes'): ?>
            <?php
            $editType = $editVenue['venue_type'] ?? 'fixed';
            $venueFormOpen = $showForm || $editVenue;
            ?>
            <section class="provider-panel">
                <div class="panel-toolbar">
                    <div>
                        <h3>Sedes y subcentros</h3>
                        <p class="muted" style="margin:0.25rem 0 0">
                            Sede fija = dirección conocida · Subcentro = ciudad/estado (lugar por aplicación)
                        </p>
                    </div>
                    <?php if (!$venueFormOpen): ?>
                        <a class="btn" href="/admin/providers/edit?id=<?= $id ?>&tab=sedes&form=1">Agregar sede / subcentro</a>
                    <?php endif; ?>
                </div>

                <?php if ($venueFormOpen): ?>
                    <div class="inline-form-panel">
                        <div class="panel-toolbar">
                            <h4 class="venue-form-title" style="margin:0"><?= $editVenue ? 'Editar' : 'Agregar' ?> sede / subcentro</h4>
                            <a class="btn btn-ghost" href="/admin/providers/edit?id=<?= $id ?>&tab=sedes">Cancelar</a>
                        </div>
                        <form method="post" action="/admin/providers/venue" class="form-grid" style="margin-top:0.75rem" id="venueForm">
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
                    </div>
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
                <?php else: ?>
                    <?php if ($venues): ?>
                        <div class="venue-cards venue-accordion">
                            <?php foreach ($venues as $v): ?>
                                <?php
                                $vActive = (int)($v['is_active'] ?? 1) === 1;
                                $isSub = ($v['venue_type'] ?? 'fixed') === 'subcentro';
                                $vid = (int)$v['id'];
                                ?>
                                <article class="venue-card accordion-item <?= $vActive ? '' : 'is-inactive' ?> <?= $isSub ? 'is-subcentro' : 'is-fixed' ?>">
                                    <button type="button" class="accordion-trigger" aria-expanded="false" data-acc="<?= $vid ?>">
                                        <span class="accordion-trigger-main">
                                            <span class="venue-type-pill"><?= $isSub ? 'Subcentro' : 'Sede fija' ?></span>
                                            <strong><?= e(trim($v['city'] . ($v['state'] ? ', ' . $v['state'] : ''))) ?></strong>
                                            <?php if (!empty($v['name'])): ?>
                                                <span class="muted venue-place"><?= e($v['name']) ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="accordion-chevron"><?= $iconChevron ?></span>
                                    </button>
                                    <div class="venue-card-actions accordion-actions" onclick="event.stopPropagation()">
                                        <a class="icon-btn" href="/admin/providers/edit?id=<?= $id ?>&tab=sedes&edit_venue=<?= $vid ?>" title="Editar" aria-label="Editar"><?= $iconEdit ?></a>
                                        <form method="post" action="/admin/providers/venue/toggle-active" class="inline-form"
                                              onsubmit="return confirm(<?= json_encode('¿Seguro que quieres ' . ($vActive ? 'desactivar' : 'activar') . ' ' . ($isSub ? 'el subcentro' : 'la sede') . ' de ' . $v['city'] . '?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);">
                                            <input type="hidden" name="provider_id" value="<?= $id ?>">
                                            <input type="hidden" name="venue_id" value="<?= $vid ?>">
                                            <button type="submit" class="icon-btn eye-btn" title="<?= $vActive ? 'Desactivar' : 'Activar' ?>" aria-label="<?= $vActive ? 'Desactivar' : 'Activar' ?>">
                                                <?= $vActive ? $iconEye : $iconEyeOff ?>
                                            </button>
                                        </form>
                                        <form method="post" action="/admin/providers/venue/delete" class="inline-form" onsubmit="return confirm('¿Eliminar de forma permanente?');">
                                            <input type="hidden" name="provider_id" value="<?= $id ?>">
                                            <input type="hidden" name="venue_id" value="<?= $vid ?>">
                                            <button type="submit" class="icon-btn icon-btn-danger" title="Eliminar" aria-label="Eliminar"><?= $iconTrash ?></button>
                                        </form>
                                    </div>
                                    <div class="accordion-panel" hidden>
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
                                        <?php if (!empty($v['notes'])): ?>
                                            <p class="muted"><?= nl2br(e($v['notes'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <script>
                        (() => {
                          document.querySelectorAll('.venue-accordion .accordion-trigger').forEach((btn) => {
                            btn.addEventListener('click', () => {
                              const card = btn.closest('.accordion-item');
                              const panel = card.querySelector('.accordion-panel');
                              const open = btn.getAttribute('aria-expanded') === 'true';
                              btn.setAttribute('aria-expanded', open ? 'false' : 'true');
                              panel.hidden = open;
                              card.classList.toggle('is-open', !open);
                            });
                          });
                        })();
                        </script>
                    <?php else: ?>
                        <p class="muted">Sin sedes ni subcentros. Ejemplo Cambridge: 2 sedes fijas en CDMX + subcentros por estado.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
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
                <h3>Convenios y acuerdos (PDF)</h3>
                <p class="muted">
                    Puedes tener <strong>varios vigentes a la vez</strong> (p. ej. ITEP supervisor + centro,
                    o UKS base + extensión CENEVAL). Descontinuar no borra el PDF.
                </p>
                <?php if ($agreements): ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Etiqueta</th><th>Año</th><th>Firmado</th><th>Estado</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($agreements as $a): ?>
                                <?php $aActive = (int)$a['is_current'] === 1; ?>
                                <tr class="<?= $aActive ? '' : 'is-row-inactive' ?>">
                                    <td>
                                        <a href="/media?f=<?= e(rawurlencode($a['file_path'])) ?>" target="_blank" rel="noopener"><?= e($a['label']) ?></a>
                                        <?php if (!empty($a['notes'])): ?>
                                            <br><small class="muted"><?= e($a['notes']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e((string)($a['year'] ?? '—')) ?></td>
                                    <td><?= e($a['signed_on'] ?? '—') ?></td>
                                    <td>
                                        <?php if ($aActive): ?>
                                            <span class="pill pill-ok">Vigente</span>
                                        <?php else: ?>
                                            <span class="pill pill-muted">Descontinuado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="icon-actions">
                                            <form method="post" action="/admin/providers/agreement/toggle-active" class="inline-form"
                                                  onsubmit="return confirm(<?= json_encode($aActive ? '¿Descontinuar este convenio? El PDF se conserva.' : '¿Reactivar este convenio como vigente?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);">
                                                <input type="hidden" name="provider_id" value="<?= $id ?>">
                                                <input type="hidden" name="agreement_id" value="<?= (int)$a['id'] ?>">
                                                <input type="hidden" name="activate" value="<?= $aActive ? '0' : '1' ?>">
                                                <button type="submit" class="icon-btn eye-btn" title="<?= $aActive ? 'Descontinuar' : 'Reactivar' ?>" aria-label="<?= $aActive ? 'Descontinuar' : 'Reactivar' ?>">
                                                    <?= $aActive ? $iconEye : $iconEyeOff ?>
                                                </button>
                                            </form>
                                            <form method="post" action="/admin/providers/agreement/delete" class="inline-form" onsubmit="return confirm('¿Eliminar el PDF de forma permanente?');">
                                                <input type="hidden" name="provider_id" value="<?= $id ?>">
                                                <input type="hidden" name="agreement_id" value="<?= (int)$a['id'] ?>">
                                                <button type="submit" class="icon-btn icon-btn-danger" title="Eliminar" aria-label="Eliminar"><?= $iconTrash ?></button>
                                            </form>
                                        </div>
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
                    <label>Etiqueta<input name="label" required placeholder="Ej. Supervisor ITEP / Extensión CENEVAL"></label>
                    <label>Año<input type="number" name="year" value="<?= e(date('Y')) ?>"></label>
                    <label>Fecha de firma<input type="date" name="signed_on"></label>
                    <label>PDF (máx. 20 MB)<input type="file" name="agreement_file" required accept=".pdf,application/pdf"></label>
                    <label>Nota corta<input name="notes" placeholder="Opcional: supervisor, centro, extensión…"></label>
                    <label class="check"><input type="checkbox" name="is_current" checked> Queda vigente (no desactiva los anteriores)</label>
                    <div class="actions"><button class="btn" type="submit">Subir acuerdo</button></div>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($item && $tab === 'cuentas'): ?>
            <?php
            $accountFormOpen = $showForm || $editAccount;
            ?>
            <section class="provider-panel">
                <div class="panel-toolbar">
                    <div>
                        <h3>Cuentas y sitios</h3>
                        <p class="muted" style="margin:0.25rem 0 0">
                            Portales con login (usuario + contraseña cifrada) o <strong>sitios sin login</strong>
                            (solo URL: registros, material, capacitación…). Solo admin.
                        </p>
                    </div>
                    <?php if (!$accountFormOpen): ?>
                        <a class="btn" href="/admin/providers/edit?id=<?= $id ?>&tab=cuentas&form=1">Agregar cuenta o sitio</a>
                    <?php endif; ?>
                </div>

                <?php if ($accountFormOpen): ?>
                    <?php $editIsSite = $editAccount && trim((string)($editAccount['username'] ?? '')) === ''; ?>
                    <div class="inline-form-panel">
                        <div class="panel-toolbar">
                            <h4 style="margin:0"><?= $editAccount ? 'Editar' : 'Nuevo' ?> · cuenta o sitio</h4>
                            <a class="btn btn-ghost" href="/admin/providers/edit?id=<?= $id ?>&tab=cuentas">Cancelar</a>
                        </div>
                        <form method="post" action="/admin/providers/account" class="form-grid" style="margin-top:0.75rem" autocomplete="off" id="accountForm">
                            <input type="hidden" name="provider_id" value="<?= $id ?>">
                            <?php if ($editAccount): ?><input type="hidden" name="account_id" value="<?= (int)$editAccount['id'] ?>"><?php endif; ?>
                            <label>Etiqueta
                                <input name="label" required value="<?= e($editAccount['label'] ?? '') ?>" placeholder="Ej. Material de estudio / Admin de exámenes">
                            </label>
                            <label>URL
                                <input type="url" name="portal_url" id="accountPortalUrl" value="<?= e($editAccount['portal_url'] ?? '') ?>" placeholder="https://…">
                                <small class="muted">Obligatoria si es un sitio (sin usuario).</small>
                            </label>
                            <label>Usuario
                                <input name="username" id="accountUsername" value="<?= e($editAccount['username'] ?? '') ?>" autocomplete="off" placeholder="Vacío = guardar como sitio">
                                <small class="muted">Si lo dejas vacío se guarda como sitio (sin contraseña).</small>
                            </label>
                            <label id="accountPasswordLabel">Contraseña
                                <span class="password-field">
                                    <input type="password" name="password" id="accountPasswordInput" autocomplete="new-password" placeholder="<?= $editAccount && !$editIsSite ? 'Dejar vacío para no cambiar' : '' ?>">
                                    <button type="button" class="icon-btn password-toggle js-toggle-input-pass" title="Mostrar/ocultar" aria-label="Mostrar u ocultar contraseña"><?= $iconEye ?></button>
                                </span>
                                <small class="muted" id="accountPasswordHint">Obligatoria si hay usuario.</small>
                            </label>
                            <label class="field-wide">Notas
                                <textarea name="notes" rows="2" placeholder="Para qué sirve, material, registro, MFA, etc."><?= e($editAccount['notes'] ?? '') ?></textarea>
                            </label>
                            <?php if ($editAccount): ?>
                                <label class="check"><input type="checkbox" name="is_active" <?= !empty($editAccount['is_active']) ? 'checked' : '' ?>> Activo</label>
                            <?php endif; ?>
                            <div class="actions">
                                <button class="btn" type="submit"><?= $editAccount ? 'Guardar cambios' : 'Guardar' ?></button>
                            </div>
                        </form>
                    </div>
                    <script>
                    (() => {
                      const user = document.getElementById('accountUsername');
                      const pass = document.getElementById('accountPasswordInput');
                      const passLabel = document.getElementById('accountPasswordLabel');
                      const url = document.getElementById('accountPortalUrl');
                      const btn = document.querySelector('.js-toggle-input-pass');
                      const eye = <?= json_encode($iconEye) ?>;
                      const eyeOff = <?= json_encode($iconEyeOff) ?>;
                      const isEdit = <?= $editAccount ? 'true' : 'false' ?>;
                      const hadLogin = <?= ($editAccount && !$editIsSite) ? 'true' : 'false' ?>;

                      const sync = () => {
                        const site = !(user?.value || '').trim();
                        if (passLabel) passLabel.style.display = site ? 'none' : '';
                        if (pass) {
                          // Nueva cuenta con usuario, o sitio→cuenta: pide contraseña.
                          pass.required = !site && (!isEdit || !hadLogin);
                          if (site) pass.value = '';
                        }
                        if (url) url.required = site;
                      };
                      user?.addEventListener('input', sync);
                      sync();

                      btn?.addEventListener('click', () => {
                        if (!pass) return;
                        const show = pass.type === 'password';
                        pass.type = show ? 'text' : 'password';
                        btn.innerHTML = show ? eyeOff : eye;
                        btn.title = show ? 'Ocultar' : 'Mostrar';
                      });
                    })();
                    </script>
                <?php else: ?>
                    <?php if ($accounts): ?>
                        <div class="account-cards" id="accountCards"
                             data-reveal-url="/admin/providers/account/reveal"
                             data-provider-id="<?= $id ?>">
                            <?php foreach ($accounts as $acc): ?>
                                <?php
                                $accActive = (int)($acc['is_active'] ?? 1) === 1;
                                $isSite = trim((string)($acc['username'] ?? '')) === '';
                                ?>
                                <article class="account-card <?= $accActive ? '' : 'is-inactive' ?> <?= $isSite ? 'is-site' : 'is-login' ?>" data-account-id="<?= (int)$acc['id'] ?>" data-is-site="<?= $isSite ? '1' : '0' ?>">
                                    <header>
                                        <div>
                                            <span class="venue-type-pill"><?= $isSite ? 'Sitio' : 'Cuenta' ?></span>
                                            <strong><?= e($acc['label']) ?></strong>
                                            <?php if (!empty($acc['portal_url'])): ?>
                                                <p class="muted" style="margin:0.2rem 0 0">
                                                    <a href="<?= e($acc['portal_url']) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#i', '', rtrim((string)$acc['portal_url'], '/'))) ?></a>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="icon-actions">
                                            <a class="icon-btn" href="/admin/providers/edit?id=<?= $id ?>&tab=cuentas&edit_account=<?= (int)$acc['id'] ?>" title="Editar" aria-label="Editar"><?= $iconEdit ?></a>
                                            <form method="post" action="/admin/providers/account/delete" class="inline-form" onsubmit="return confirm('¿Eliminar este registro?');">
                                                <input type="hidden" name="provider_id" value="<?= $id ?>">
                                                <input type="hidden" name="account_id" value="<?= (int)$acc['id'] ?>">
                                                <button type="submit" class="icon-btn icon-btn-danger" title="Eliminar" aria-label="Eliminar"><?= $iconTrash ?></button>
                                            </form>
                                        </div>
                                    </header>
                                    <?php if (!$isSite): ?>
                                        <dl class="account-creds">
                                            <div>
                                                <dt>Usuario</dt>
                                                <dd>
                                                    <code class="js-username"><?= e($acc['username']) ?></code>
                                                    <button type="button" class="icon-btn js-copy-user" title="Copiar usuario" aria-label="Copiar usuario"><?= $iconCopy ?></button>
                                                </dd>
                                            </div>
                                            <div>
                                                <dt>Contraseña</dt>
                                                <dd>
                                                    <code class="secret-mask js-secret">••••••••</code>
                                                    <button type="button" class="icon-btn js-toggle-secret" title="Mostrar contraseña" aria-label="Mostrar contraseña" aria-pressed="false"><?= $iconEye ?></button>
                                                    <button type="button" class="icon-btn js-copy-pass" title="Copiar contraseña" aria-label="Copiar contraseña"><?= $iconCopy ?></button>
                                                </dd>
                                            </div>
                                        </dl>
                                    <?php endif; ?>
                                    <?php if (!empty($acc['notes'])): ?>
                                        <p class="muted"><?= nl2br(e($acc['notes'])) ?></p>
                                    <?php endif; ?>
                                    <?php if (!$accActive): ?>
                                        <p class="muted"><em>Inactivo</em></p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <div class="modal-backdrop" id="revealPassModal" hidden>
                            <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="revealPassTitle">
                                <h3 id="revealPassTitle">Confirma tu contraseña</h3>
                                <p class="muted">Para ver o copiar la contraseña del portal, introduce tu contraseña del sistema.</p>
                                <form id="revealPassForm" class="stack" autocomplete="off">
                                    <label>Contraseña del sistema
                                        <input type="password" name="system_password" id="revealSystemPass" required autocomplete="current-password">
                                    </label>
                                    <p class="form-error" id="revealPassError" hidden></p>
                                    <div class="actions">
                                        <button class="btn" type="submit">Continuar</button>
                                        <button class="btn btn-ghost" type="button" id="revealPassCancel">Cancelar</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <script>
                        (() => {
                          const root = document.getElementById('accountCards');
                          if (!root) return;
                          const eye = <?= json_encode($iconEye) ?>;
                          const eyeOff = <?= json_encode($iconEyeOff) ?>;
                          const modal = document.getElementById('revealPassModal');
                          const form = document.getElementById('revealPassForm');
                          const input = document.getElementById('revealSystemPass');
                          const err = document.getElementById('revealPassError');
                          let pending = null; // { accountId, action: 'reveal'|'copy', card }

                          const hideModal = () => {
                            modal.hidden = true;
                            form.reset();
                            err.hidden = true;
                            err.textContent = '';
                            pending = null;
                          };

                          const showModal = (payload) => {
                            pending = payload;
                            err.hidden = true;
                            modal.hidden = false;
                            setTimeout(() => input.focus(), 50);
                          };

                          const copyText = async (text) => {
                            try {
                              await navigator.clipboard.writeText(text);
                              return true;
                            } catch (e) {
                              const ta = document.createElement('textarea');
                              ta.value = text;
                              document.body.appendChild(ta);
                              ta.select();
                              const ok = document.execCommand('copy');
                              ta.remove();
                              return ok;
                            }
                          };

                          const revealRequest = async (accountId, systemPassword) => {
                            const body = new FormData();
                            body.append('provider_id', root.dataset.providerId);
                            body.append('account_id', String(accountId));
                            body.append('system_password', systemPassword);
                            const res = await fetch(root.dataset.revealUrl, {
                              method: 'POST',
                              body,
                              credentials: 'same-origin',
                              headers: { 'Accept': 'application/json' }
                            });
                            const data = await res.json().catch(() => ({ ok: false, error: 'Respuesta inválida' }));
                            if (!res.ok || !data.ok) {
                              throw new Error(data.error || 'No se pudo revelar la contraseña.');
                            }
                            return data.password || '';
                          };

                          const applyReveal = (card, password) => {
                            const code = card.querySelector('.js-secret');
                            const btn = card.querySelector('.js-toggle-secret');
                            code.textContent = password;
                            code.dataset.revealed = password;
                            code.classList.add('is-revealed');
                            btn.innerHTML = eyeOff;
                            btn.title = 'Ocultar contraseña';
                            btn.setAttribute('aria-pressed', 'true');
                          };

                          const hideSecret = (card) => {
                            const code = card.querySelector('.js-secret');
                            const btn = card.querySelector('.js-toggle-secret');
                            code.textContent = '••••••••';
                            delete code.dataset.revealed;
                            code.classList.remove('is-revealed');
                            btn.innerHTML = eye;
                            btn.title = 'Mostrar contraseña';
                            btn.setAttribute('aria-pressed', 'false');
                          };

                          root.addEventListener('click', async (e) => {
                            const card = e.target.closest('.account-card');
                            if (!card) return;
                            const accountId = card.dataset.accountId;

                            if (e.target.closest('.js-copy-user')) {
                              const user = card.querySelector('.js-username')?.textContent || '';
                              await copyText(user);
                              return;
                            }

                            if (e.target.closest('.js-toggle-secret')) {
                              const code = card.querySelector('.js-secret');
                              if (code.dataset.revealed) {
                                hideSecret(card);
                                return;
                              }
                              showModal({ accountId, action: 'reveal', card });
                              return;
                            }

                            if (e.target.closest('.js-copy-pass')) {
                              const code = card.querySelector('.js-secret');
                              if (code.dataset.revealed) {
                                await copyText(code.dataset.revealed);
                                return;
                              }
                              showModal({ accountId, action: 'copy', card });
                            }
                          });

                          document.getElementById('revealPassCancel')?.addEventListener('click', hideModal);
                          modal?.addEventListener('click', (e) => {
                            if (e.target === modal) hideModal();
                          });

                          form?.addEventListener('submit', async (e) => {
                            e.preventDefault();
                            if (!pending) return;
                            err.hidden = true;
                            try {
                              const password = await revealRequest(pending.accountId, input.value);
                              if (pending.action === 'reveal') {
                                applyReveal(pending.card, password);
                              } else if (pending.action === 'copy') {
                                applyReveal(pending.card, password);
                                await copyText(password);
                              }
                              hideModal();
                            } catch (ex) {
                              err.textContent = ex.message || 'Error';
                              err.hidden = false;
                            }
                          });
                        })();
                        </script>
                    <?php else: ?>
                        <p class="muted">Sin registros. Agrega cuentas con login o sitios (solo URL) para material, capacitación, etc.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($item && $tab === 'certificaciones'): ?>
            <?php $certFormOpen = $showForm; ?>
            <section class="provider-panel">
                <div class="panel-toolbar">
                    <div>
                        <h3>Certificaciones</h3>
                        <p class="muted" style="margin:0.25rem 0 0">Listado rápido. El detalle se completa en Certificaciones.</p>
                    </div>
                    <?php if (!$certFormOpen): ?>
                        <a class="btn" href="/admin/providers/edit?id=<?= $id ?>&tab=certificaciones&form=1">Agregar certificaciones</a>
                    <?php endif; ?>
                </div>

                <?php if ($certFormOpen): ?>
                    <div class="inline-form-panel">
                        <div class="panel-toolbar">
                            <h4 style="margin:0">Agregar varias (solo nombre)</h4>
                            <a class="btn btn-ghost" href="/admin/providers/edit?id=<?= $id ?>&tab=certificaciones">Cancelar</a>
                        </div>
                        <form method="post" action="/admin/providers/certifications" style="margin-top:0.75rem">
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
                    </div>
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
                <?php else: ?>
                    <?php if ($certifications): ?>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead><tr><th>Nombre</th><th>Código</th><th>Grupo</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($certifications as $c): ?>
                                    <?php $pub = (int)$c['is_published'] === 1; ?>
                                    <tr class="<?= $pub ? '' : 'is-row-inactive' ?>">
                                        <td><?= e($c['name']) ?></td>
                                        <td><code><?= e($c['code']) ?></code></td>
                                        <td><?= e($c['group_name'] ?? '—') ?></td>
                                        <td>
                                            <div class="icon-actions">
                                                <form method="post" action="/admin/providers/certification/toggle-published" class="inline-form"
                                                      onsubmit="return confirm(<?= json_encode('¿' . ($pub ? 'Ocultar' : 'Publicar') . ' “' . $c['name'] . '”?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);">
                                                    <input type="hidden" name="provider_id" value="<?= $id ?>">
                                                    <input type="hidden" name="certification_id" value="<?= (int)$c['id'] ?>">
                                                    <button type="submit" class="icon-btn eye-btn" title="<?= $pub ? 'Ocultar (despublicar)' : 'Publicar' ?>" aria-label="<?= $pub ? 'Ocultar' : 'Publicar' ?>">
                                                        <?= $pub ? $iconEye : $iconEyeOff ?>
                                                    </button>
                                                </form>
                                                <a class="icon-btn" href="/admin/certifications/edit?id=<?= (int)$c['id'] ?>" title="Editar ficha" aria-label="Editar ficha"><?= $iconEdit ?></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="muted">Sin certificaciones aún.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($item && $tab === 'grupos'): ?>
            <?php require __DIR__ . '/_tab_grupos.php'; ?>
        <?php endif; ?>

        <?php if ($item && $tab === 'documentos'): ?>
            <?php require __DIR__ . '/_tab_documentos.php'; ?>
        <?php endif; ?>

        <?php if ($item && $tab === 'campos'): ?>
            <?php require __DIR__ . '/_tab_campos.php'; ?>
        <?php endif; ?>

        <?php if ($item && $tab === 'notas'): ?>
            <?php $noteFormOpen = $showForm; ?>
            <section class="provider-panel">
                <div class="panel-toolbar">
                    <div>
                        <h3>Notas</h3>
                        <p class="muted" style="margin:0.25rem 0 0">Bitácora interna: fecha y autor en cada nota.</p>
                    </div>
                    <?php if (!$noteFormOpen): ?>
                        <a class="btn" href="/admin/providers/edit?id=<?= $id ?>&tab=notas&form=1">Nueva nota</a>
                    <?php endif; ?>
                </div>

                <?php if ($noteFormOpen): ?>
                    <div class="inline-form-panel">
                        <div class="panel-toolbar">
                            <h4 style="margin:0">Escribir nota</h4>
                            <a class="btn btn-ghost" href="/admin/providers/edit?id=<?= $id ?>&tab=notas">Cancelar</a>
                        </div>
                        <form method="post" action="/admin/providers/note" class="form-grid" style="margin-top:0.75rem">
                            <input type="hidden" name="provider_id" value="<?= $id ?>">
                            <label style="grid-column:1/-1">Nota<textarea name="body" rows="4" required placeholder="Escribe la nota…"></textarea></label>
                            <div class="actions"><button class="btn" type="submit">Guardar nota</button></div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="notes-timeline">
                        <?php foreach ($notes as $n): ?>
                            <article class="note-entry">
                                <header>
                                    <strong><?= e($n['author_name'] ?? $n['author_email'] ?? 'Usuario') ?></strong>
                                    <time><?= e($n['created_at']) ?></time>
                                    <form method="post" action="/admin/providers/note/delete" class="inline-form" onsubmit="return confirm('¿Eliminar nota?');">
                                        <input type="hidden" name="provider_id" value="<?= $id ?>">
                                        <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>">
                                        <button type="submit" class="icon-btn icon-btn-danger" title="Eliminar" aria-label="Eliminar"><?= $iconTrash ?></button>
                                    </form>
                                </header>
                                <p><?= nl2br(e($n['body'])) ?></p>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$notes): ?><p class="muted">Sin notas aún.</p><?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>

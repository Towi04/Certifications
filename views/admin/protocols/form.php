<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$steps = $steps ?? [];
$phases = $phases ?? [];
$responsibles = $responsibles ?? [];
$editStepId = (int) ($_GET['step'] ?? 0);
$editStep = null;
foreach ($steps as $s) {
    if ((int) $s['id'] === $editStepId) {
        $editStep = $s;
        break;
    }
}

$tab = $tab ?? (string) ($_GET['tab'] ?? 'general');
$allowed = $item
    ? ['general' => 'General', 'requisitos' => 'Requisitos', 'correos' => 'Correos', 'acciones' => 'Acciones', 'pasos' => 'Pasos']
    : ['general' => 'General'];
if (!isset($allowed[$tab])) {
    $tab = array_key_first($allowed);
}
if ($editStepId > 0 && $item) {
    $tab = 'pasos';
}

$protocolId = $item ? (int) $item['id'] : 0;
$fichaTitle = $item ? (string) ($item['name'] ?? 'Protocolo') : 'Nuevo protocolo';
$fichaSubtitle = $item
    ? '<code>' . e((string) ($item['code'] ?? '')) . '</code>'
    : 'Un protocolo es el <strong>flujo de pasos</strong> para presentar una certificación o curso.';
$fichaBackUrl = '/admin/protocols';
$fichaTabBase = $protocolId > 0 ? '/admin/protocols/edit?id=' . $protocolId : '';
$fichaMode = 'js';
$tabs = $allowed;
$workflow_actions = $workflow_actions ?? [];
$protocol_action_ids = $protocol_action_ids ?? [];
?>
<section class="admin-ficha" data-admin-ficha data-tab="<?= e($tab) ?>">
    <?php require __DIR__ . '/../_ficha_head.php'; ?>

    <?php if (!empty($info)): ?><p class="alert alert-ok"><?= e($info) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

    <form method="post" action="/admin/protocols/save" id="protocolForm" class="stack">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= $protocolId ?>"><?php endif; ?>
        <input type="hidden" name="tab" value="<?= e($tab) ?>">

        <div class="admin-ficha-panel" data-tab-panel="general" <?= $tab !== 'general' ? 'hidden' : '' ?>>
            <h3>Datos generales</h3>
            <p class="muted">
                Cada paso del flujo tiene un responsable (alumno, admin, TR, certificadora, SEP o sistema).
            </p>
            <div class="form-grid">
                <label>Proveedor / certificadora
                    <select name="provider_id">
                        <option value="">—</option>
                        <?php foreach ($providers as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= (int)($item['provider_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Código<input name="code" required value="<?= e($item['code'] ?? '') ?>"></label>
                <label>Nombre<input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
                <label>Modalidad
                    <select name="modality">
                        <?php foreach (['online','paper','hybrid','inventory','other'] as $m): ?>
                            <option value="<?= $m ?>" <?= ($item['modality'] ?? 'online') === $m ? 'selected' : '' ?>><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field-wide">Resumen (HTML, opcional)<textarea name="procedure_html" rows="4"><?= e($item['procedure_html'] ?? '') ?></textarea></label>
                <label class="check field-wide"><input type="checkbox" name="is_active" <?= !isset($item) || (int)($item['is_active'] ?? 1) ? 'checked' : '' ?>> Activo</label>
            </div>
        </div>

        <div class="admin-ficha-panel" data-tab-panel="requisitos" <?= $tab !== 'requisitos' ? 'hidden' : '' ?>>
            <h3>Requisitos del protocolo</h3>
            <div class="form-grid">
                <label class="check"><input type="checkbox" name="requires_regulation_signature" <?= !empty($item['requires_regulation_signature']) ? 'checked' : '' ?>> Incluye firma de reglamento</label>
                <label class="check"><input type="checkbox" name="requires_software" <?= !empty($item['requires_software']) ? 'checked' : '' ?>> Software</label>
                <label class="check"><input type="checkbox" name="requires_zoom" <?= !empty($item['requires_zoom']) ? 'checked' : '' ?>> Zoom</label>
                <label class="check"><input type="checkbox" name="requires_vm" <?= !empty($item['requires_vm']) ? 'checked' : '' ?>> Máquina virtual</label>
                <label class="check"><input type="checkbox" name="uses_inventory" <?= !empty($item['uses_inventory']) ? 'checked' : '' ?>> Inventario de códigos</label>
            </div>
        </div>

        <div class="admin-ficha-panel" data-tab-panel="correos" <?= $tab !== 'correos' ? 'hidden' : '' ?>>
            <h3>Correos y exportación</h3>
            <div class="form-grid">
                <label>Formato exportación proveedor
                    <select name="export_format">
                        <?php foreach (($export_formats ?? ['none' => 'Ninguno']) as $code => $label): ?>
                            <option value="<?= e($code) ?>" <?= ($item['export_format'] ?? 'none') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field-wide">Plantilla solicitud a empresa
                    <select name="provider_request_template">
                        <option value="">— ninguna —</option>
                        <?php foreach (($mail_templates ?? []) as $tpl): ?>
                            <?php if (($tpl['audience'] ?? '') !== 'provider' && ($tpl['to_mode'] ?? '') !== 'provider') continue; ?>
                            <option value="<?= e($tpl['code']) ?>" <?= ($item['provider_request_template'] ?? '') === $tpl['code'] ? 'selected' : '' ?>><?= e($tpl['name']) ?> (<?= e($tpl['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small class="muted">
                        Es el correo que se envía al proveedor al confirmar pago / solicitar examen
                        (con exportación CSV/Excel y comprobante si existen). Ej. <code>uks_solicitud</code>.
                    </small>
                </label>
                <label class="field-wide">Plantilla datos de acceso (alumno)
                    <select name="student_access_template">
                        <option value="">— ninguna —</option>
                        <?php foreach (($mail_templates ?? []) as $tpl): ?>
                            <option value="<?= e($tpl['code']) ?>" <?= ($item['student_access_template'] ?? '') === $tpl['code'] ? 'selected' : '' ?>><?= e($tpl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="muted">Se envía manualmente desde el caso cuando ya tienes folio/clave (o Moodle usa <code>moodle_acceso</code>).</small>
                </label>
            </div>
        </div>

        <div class="admin-ficha-panel" data-tab-panel="acciones" <?= $tab !== 'acciones' ? 'hidden' : '' ?>>
            <h3>Acciones del protocolo</h3>
            <p class="muted">
                Elige del catálogo <a href="/admin/actions">Acciones</a>. El orden de los checks (arriba→abajo en la lista)
                define el orden de los botones en Casos. Cada acción puede ser botón y/o trigger automático.
            </p>
            <?php if ($workflow_actions): ?>
                <div class="reg-fields-grid">
                    <?php foreach ($workflow_actions as $wa): ?>
                        <label class="check">
                            <input type="checkbox" name="action_ids[]" value="<?= (int)$wa['id'] ?>"
                                <?= in_array((int)$wa['id'], array_map('intval', $protocol_action_ids), true) ? 'checked' : '' ?>>
                            <?= e($wa['name']) ?>
                            <code class="muted"><?= e($wa['code']) ?></code>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted">Aún no hay acciones. Crea algunas en Admin → Acciones.</p>
            <?php endif; ?>
        </div>

        <div class="admin-ficha-actions">
            <button class="btn" type="submit">Guardar protocolo</button>
        </div>
    </form>

    <?php if ($item): ?>
    <div class="admin-ficha-panel" data-tab-panel="pasos" <?= $tab !== 'pasos' ? 'hidden' : '' ?>>
        <h3>Pasos del flujo</h3>
        <p class="muted">
            Ordenados como los vive el alumno. Usa <strong>↑ / ↓</strong> o arrastra el asa ⋮⋮ para cambiar el orden;
            al guardar se renumeran 1…n y se actualiza el timeline de los casos abiertos.
        </p>

        <?php if ($steps): ?>
            <?php $stepCount = count($steps); ?>
            <form method="post" action="/admin/protocols/steps/reorder" id="protocolReorderForm">
                <input type="hidden" name="protocol_id" value="<?= $protocolId ?>">
                <ol class="protocol-timeline protocol-timeline--sortable" id="protocolStepsList">
                    <?php foreach ($steps as $index => $step):
                        $phase = (string) $step['phase'];
                    ?>
                        <li class="protocol-step <?= (int)$step['is_active'] ? '' : 'is-inactive' ?>"
                            draggable="true"
                            data-step-id="<?= (int)$step['id'] ?>">
                            <input type="hidden" name="step_order[]" value="<?= (int)$step['id'] ?>">
                            <div class="protocol-step-head">
                                <span class="protocol-drag-handle" title="Arrastrar para reordenar" aria-hidden="true">⋮⋮</span>
                                <span class="protocol-step-num"><?= $index + 1 ?></span>
                                <strong><?= e($step['title']) ?></strong>
                                <span class="pill"><?= e($phases[$phase] ?? $phase) ?></span>
                                <span class="pill"><?= e($responsibles[$step['responsible']] ?? $step['responsible']) ?></span>
                            </div>
                            <?php if (!empty($step['description'])): ?>
                                <p class="muted"><?= e($step['description']) ?></p>
                            <?php endif; ?>
                            <?php if ($step['trigger_days_after_exam'] !== null && $step['trigger_days_after_exam'] !== ''): ?>
                                <p class="muted">Plazo: <?= (int)$step['trigger_days_after_exam'] ?> días después del examen</p>
                            <?php endif; ?>
                            <div class="actions">
                                <button class="btn btn-ghost" type="submit" form="moveStep<?= (int)$step['id'] ?>Up" <?= $index === 0 ? 'disabled' : '' ?> title="Subir">↑</button>
                                <button class="btn btn-ghost" type="submit" form="moveStep<?= (int)$step['id'] ?>Down" <?= $index >= $stepCount - 1 ? 'disabled' : '' ?> title="Bajar">↓</button>
                                <a class="btn btn-ghost" href="/admin/protocols/edit?id=<?= $protocolId ?>&amp;tab=pasos&amp;step=<?= (int)$step['id'] ?>">Editar</a>
                                <button class="btn btn-ghost" type="submit" form="deleteStep<?= (int)$step['id'] ?>" onclick="return confirm('¿Eliminar este paso?');">Eliminar</button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <div class="actions" style="margin-top:1rem">
                    <button class="btn" type="submit" id="protocolReorderSave" disabled>Guardar nuevo orden</button>
                    <span class="muted" id="protocolReorderHint">Arrastra pasos o usa ↑ ↓</span>
                </div>
            </form>
            <?php foreach ($steps as $index => $step): ?>
                <form id="moveStep<?= (int)$step['id'] ?>Up" method="post" action="/admin/protocols/steps/move" class="hidden-form">
                    <input type="hidden" name="protocol_id" value="<?= $protocolId ?>">
                    <input type="hidden" name="step_id" value="<?= (int)$step['id'] ?>">
                    <input type="hidden" name="direction" value="up">
                </form>
                <form id="moveStep<?= (int)$step['id'] ?>Down" method="post" action="/admin/protocols/steps/move" class="hidden-form">
                    <input type="hidden" name="protocol_id" value="<?= $protocolId ?>">
                    <input type="hidden" name="step_id" value="<?= (int)$step['id'] ?>">
                    <input type="hidden" name="direction" value="down">
                </form>
                <form id="deleteStep<?= (int)$step['id'] ?>" method="post" action="/admin/protocols/steps/delete" class="hidden-form">
                    <input type="hidden" name="protocol_id" value="<?= $protocolId ?>">
                    <input type="hidden" name="step_id" value="<?= (int)$step['id'] ?>">
                </form>
            <?php endforeach; ?>
            <script>
            (function () {
              const list = document.getElementById('protocolStepsList');
              const saveBtn = document.getElementById('protocolReorderSave');
              const hint = document.getElementById('protocolReorderHint');
              if (!list || !saveBtn) return;
              let dragEl = null;
              const markDirty = () => {
                saveBtn.disabled = false;
                if (hint) hint.textContent = 'Hay cambios de orden sin guardar.';
                renumber();
              };
              const renumber = () => {
                let n = 0;
                list.querySelectorAll('.protocol-step').forEach((li) => {
                  n += 1;
                  const num = li.querySelector('.protocol-step-num');
                  if (num) num.textContent = String(n);
                  const input = li.querySelector('input[name="step_order[]"]');
                  if (input) input.value = li.getAttribute('data-step-id') || '';
                });
              };
              list.querySelectorAll('.protocol-step').forEach((li) => {
                li.addEventListener('dragstart', (e) => {
                  dragEl = li;
                  li.classList.add('is-dragging');
                  if (e.dataTransfer) {
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', li.getAttribute('data-step-id') || '');
                  }
                });
                li.addEventListener('dragend', () => {
                  li.classList.remove('is-dragging');
                  list.querySelectorAll('.protocol-step').forEach((x) => x.classList.remove('drag-over'));
                  dragEl = null;
                });
                li.addEventListener('dragover', (e) => {
                  e.preventDefault();
                  if (!dragEl || dragEl === li) return;
                  li.classList.add('drag-over');
                  const rect = li.getBoundingClientRect();
                  const before = (e.clientY - rect.top) < rect.height / 2;
                  if (before) list.insertBefore(dragEl, li);
                  else list.insertBefore(dragEl, li.nextSibling);
                  markDirty();
                });
                li.addEventListener('dragleave', () => li.classList.remove('drag-over'));
                li.addEventListener('drop', (e) => {
                  e.preventDefault();
                  li.classList.remove('drag-over');
                  markDirty();
                });
              });
            })();
            </script>
        <?php else: ?>
            <p class="muted">Aún no hay pasos. Agrega el primero abajo (ej. ELET tiene 19).</p>
        <?php endif; ?>

        <h4 id="step-form"><?= $editStep ? 'Editar paso' : 'Agregar paso' ?></h4>
        <form method="post" action="/admin/protocols/steps/save" class="stack form-grid">
            <input type="hidden" name="protocol_id" value="<?= $protocolId ?>">
            <?php if ($editStep): ?><input type="hidden" name="step_id" value="<?= (int)$editStep['id'] ?>"><?php endif; ?>
            <label>Orden (opcional; preferible usar ↑↓ o arrastrar)
                <input type="number" name="sort_order" min="1" value="<?= e((string)($editStep['sort_order'] ?? count($steps) + 1)) ?>">
            </label>
            <label>Fase
                <select name="phase">
                    <?php foreach ($phases as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($editStep['phase'] ?? 'pre_exam') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Responsable
                <select name="responsible">
                    <?php foreach ($responsibles as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($editStep['responsible'] ?? 'student') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Título<input name="title" required value="<?= e($editStep['title'] ?? '') ?>"></label>
            <label>Descripción<textarea name="description" rows="3"><?= e($editStep['description'] ?? '') ?></textarea></label>
            <label>Días tras examen (opcional)<input type="number" name="trigger_days_after_exam" min="0" value="<?= e((string)($editStep['trigger_days_after_exam'] ?? '')) ?>" placeholder="ej. 10, 15, 20"></label>
            <label class="check"><input type="checkbox" name="is_active" <?= !isset($editStep) || (int)($editStep['is_active'] ?? 1) ? 'checked' : '' ?>> Activo</label>
            <div class="actions">
                <button class="btn" type="submit"><?= $editStep ? 'Actualizar paso' : 'Agregar paso' ?></button>
                <?php if ($editStep): ?><a class="btn btn-ghost" href="/admin/protocols/edit?id=<?= $protocolId ?>&amp;tab=pasos">Cancelar edición</a><?php endif; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>
</section>

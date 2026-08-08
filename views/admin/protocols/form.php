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
?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <p class="muted">
        Un protocolo es el <strong>flujo de pasos</strong> para presentar una certificación o curso
        (pre-examen, durante y post-examen). Cada paso tiene un responsable (alumno, admin, TR, certificadora, SEP o sistema).
    </p>
    <form method="post" action="/admin/protocols/save" class="stack form-grid">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
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
        <label>Resumen (HTML, opcional)<textarea name="procedure_html" rows="4"><?= e($item['procedure_html'] ?? '') ?></textarea></label>
        <label class="check"><input type="checkbox" name="requires_regulation_signature" <?= !empty($item['requires_regulation_signature']) ? 'checked' : '' ?>> Incluye firma de reglamento</label>
        <label class="check"><input type="checkbox" name="requires_software" <?= !empty($item['requires_software']) ? 'checked' : '' ?>> Software</label>
        <label class="check"><input type="checkbox" name="requires_zoom" <?= !empty($item['requires_zoom']) ? 'checked' : '' ?>> Zoom</label>
        <label class="check"><input type="checkbox" name="requires_vm" <?= !empty($item['requires_vm']) ? 'checked' : '' ?>> Máquina virtual</label>
        <label class="check"><input type="checkbox" name="uses_inventory" <?= !empty($item['uses_inventory']) ? 'checked' : '' ?>> Inventario de códigos</label>
        <label>Formato exportación proveedor
            <select name="export_format">
                <?php foreach (($export_formats ?? ['none' => 'Ninguno']) as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= ($item['export_format'] ?? 'none') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Plantilla solicitud a empresa
            <select name="provider_request_template">
                <option value="">— ninguna —</option>
                <?php foreach (($mail_templates ?? []) as $tpl): ?>
                    <?php if (($tpl['audience'] ?? '') !== 'provider' && ($tpl['to_mode'] ?? '') !== 'provider') continue; ?>
                    <option value="<?= e($tpl['code']) ?>" <?= ($item['provider_request_template'] ?? '') === $tpl['code'] ? 'selected' : '' ?>><?= e($tpl['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Plantilla datos de acceso (alumno)
            <select name="student_access_template">
                <option value="">— ninguna —</option>
                <?php foreach (($mail_templates ?? []) as $tpl): ?>
                    <option value="<?= e($tpl['code']) ?>" <?= ($item['student_access_template'] ?? '') === $tpl['code'] ? 'selected' : '' ?>><?= e($tpl['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="check"><input type="checkbox" name="is_active" <?= !isset($item) || (int)($item['is_active'] ?? 1) ? 'checked' : '' ?>> Activo</label>
        <div class="actions"><button class="btn" type="submit">Guardar protocolo</button><a class="btn btn-ghost" href="/admin/protocols">Volver</a></div>
    </form>
</section>

<?php if ($item): ?>
<section class="note">
    <h2>Pasos del flujo</h2>
    <p class="muted">Ordenados como los vive el alumno. Al abrir un <em>caso</em>, el sistema marca en qué paso va.</p>

    <?php if ($steps): ?>
        <ol class="protocol-timeline">
            <?php
            $lastPhase = null;
            foreach ($steps as $step):
                $phase = (string) $step['phase'];
                if ($phase !== $lastPhase):
                    $lastPhase = $phase;
            ?>
                <li class="protocol-phase-label"><?= e($phases[$phase] ?? $phase) ?></li>
            <?php endif; ?>
                <li class="protocol-step <?= (int)$step['is_active'] ? '' : 'is-inactive' ?>">
                    <div class="protocol-step-head">
                        <span class="protocol-step-num"><?= (int)$step['sort_order'] ?></span>
                        <strong><?= e($step['title']) ?></strong>
                        <span class="pill"><?= e($responsibles[$step['responsible']] ?? $step['responsible']) ?></span>
                    </div>
                    <?php if (!empty($step['description'])): ?>
                        <p class="muted"><?= e($step['description']) ?></p>
                    <?php endif; ?>
                    <?php if ($step['trigger_days_after_exam'] !== null && $step['trigger_days_after_exam'] !== ''): ?>
                        <p class="muted">Plazo: <?= (int)$step['trigger_days_after_exam'] ?> días después del examen</p>
                    <?php endif; ?>
                    <div class="actions">
                        <a class="btn btn-ghost" href="/admin/protocols/edit?id=<?= (int)$item['id'] ?>&amp;step=<?= (int)$step['id'] ?>#step-form">Editar</a>
                        <form method="post" action="/admin/protocols/steps/delete" onsubmit="return confirm('¿Eliminar este paso?');">
                            <input type="hidden" name="protocol_id" value="<?= (int)$item['id'] ?>">
                            <input type="hidden" name="step_id" value="<?= (int)$step['id'] ?>">
                            <button class="btn btn-ghost" type="submit">Eliminar</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php else: ?>
        <p class="muted">Aún no hay pasos. Agrega el primero abajo (ej. ELET tiene 19).</p>
    <?php endif; ?>

    <h3 id="step-form"><?= $editStep ? 'Editar paso' : 'Agregar paso' ?></h3>
    <form method="post" action="/admin/protocols/steps/save" class="stack form-grid">
        <input type="hidden" name="protocol_id" value="<?= (int)$item['id'] ?>">
        <?php if ($editStep): ?><input type="hidden" name="step_id" value="<?= (int)$editStep['id'] ?>"><?php endif; ?>
        <label>Orden<input type="number" name="sort_order" min="1" value="<?= e((string)($editStep['sort_order'] ?? count($steps) + 1)) ?>"></label>
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
            <?php if ($editStep): ?><a class="btn btn-ghost" href="/admin/protocols/edit?id=<?= (int)$item['id'] ?>">Cancelar edición</a><?php endif; ?>
        </div>
    </form>
</section>
<?php endif; ?>

<?php
$groupFormOpen = $showForm || $editGroup;
$assignedByGroup = [];
foreach ($certifications as $c) {
    $gid = (int) ($c['provider_group_id'] ?? 0);
    if ($gid > 0) {
        $assignedByGroup[$gid][] = (int) $c['id'];
    }
}
?>
<section class="provider-panel">
    <div class="panel-toolbar">
        <div>
            <h3>Grupos</h3>
            <p class="muted" style="margin:0.25rem 0 0">Agrupa certificaciones (p. ej. por línea de producto o región).</p>
        </div>
        <?php if (!$groupFormOpen): ?>
            <a class="btn" href="/admin/providers/edit?id=<?= $id ?>&tab=grupos&form=1">Nuevo grupo</a>
        <?php endif; ?>
    </div>

    <?php if ($groupFormOpen): ?>
        <div class="inline-form-panel">
            <div class="panel-toolbar">
                <h4 style="margin:0"><?= $editGroup ? 'Editar grupo' : 'Nuevo grupo' ?></h4>
                <a class="btn btn-ghost" href="/admin/providers/edit?id=<?= $id ?>&tab=grupos">Cancelar</a>
            </div>
            <form method="post" action="/admin/providers/group/save" class="form-grid" style="margin-top:0.75rem">
                <input type="hidden" name="provider_id" value="<?= $id ?>">
                <?php if ($editGroup): ?><input type="hidden" name="id" value="<?= (int)$editGroup['id'] ?>"><?php endif; ?>
                <label>Nombre<input name="name" required value="<?= e($editGroup['name'] ?? '') ?>" placeholder="Ej. General, ITEP, UKS"></label>
                <label>Código
                    <input name="code" value="<?= e($editGroup['code'] ?? '') ?>" placeholder="Opcional — se genera del nombre">
                </label>
                <label class="field-wide">Descripción
                    <textarea name="description" rows="2"><?= e($editGroup['description'] ?? '') ?></textarea>
                </label>
                <label class="check"><input type="checkbox" name="is_active" <?= !isset($editGroup) || (int)($editGroup['is_active'] ?? 1) ? 'checked' : '' ?>> Activo</label>
                <div class="actions"><button class="btn" type="submit"><?= $editGroup ? 'Guardar cambios' : 'Crear grupo' ?></button></div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($groups): ?>
        <div class="table-wrap" style="margin-top:1rem">
            <table class="data-table">
                <thead><tr><th>Nombre</th><th>Código</th><th>Certificaciones</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($groups as $g): ?>
                    <?php $gActive = (int)($g['is_active'] ?? 1) === 1; ?>
                    <tr class="<?= $gActive ? '' : 'is-row-inactive' ?>">
                        <td>
                            <strong><?= e($g['name']) ?></strong>
                            <?php if (!empty($g['description'])): ?>
                                <br><small class="muted"><?= e($g['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><code><?= e($g['code']) ?></code></td>
                        <td><?= (int)($g['certifications_count'] ?? 0) ?></td>
                        <td>
                            <div class="icon-actions">
                                <a class="icon-btn" href="/admin/providers/edit?id=<?= $id ?>&tab=grupos&edit_group=<?= (int)$g['id'] ?>" title="Editar" aria-label="Editar"><?= $iconEdit ?></a>
                                <form method="post" action="/admin/providers/group/delete" class="inline-form"
                                      onsubmit="return confirm(<?= json_encode('¿Eliminar el grupo “' . $g['name'] . '”? Las certificaciones quedarán sin grupo.', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);">
                                    <input type="hidden" name="provider_id" value="<?= $id ?>">
                                    <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
                                    <button type="submit" class="icon-btn icon-btn-danger" title="Eliminar" aria-label="Eliminar"><?= $iconTrash ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php foreach ($groups as $g): ?>
            <?php
            $gid = (int) $g['id'];
            $assigned = $assignedByGroup[$gid] ?? [];
            ?>
            <div class="inline-form-panel" style="margin-top:1rem">
                <h4 style="margin:0 0 0.5rem">Certificaciones en «<?= e($g['name']) ?>»</h4>
                <form method="post" action="/admin/providers/group/assign" class="form-grid">
                    <input type="hidden" name="provider_id" value="<?= $id ?>">
                    <input type="hidden" name="group_id" value="<?= $gid ?>">
                    <div class="field-wide">
                        <?php if ($certifications): ?>
                            <div class="reg-fields-grid">
                                <?php foreach ($certifications as $c): ?>
                                    <label class="check">
                                        <input type="checkbox" name="certification_ids[]" value="<?= (int)$c['id'] ?>"
                                            <?= in_array((int)$c['id'], $assigned, true) ? 'checked' : '' ?>>
                                        <?= e($c['name']) ?> <code class="muted"><?= e($c['code']) ?></code>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="muted">Sin certificaciones para asignar.</p>
                        <?php endif; ?>
                    </div>
                    <div class="actions">
                        <button class="btn btn-ghost" type="submit">Guardar asignación</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="muted">Sin grupos. Se crea uno «General» automáticamente al guardar el proveedor.</p>
    <?php endif; ?>
</section>

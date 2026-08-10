<?php
$groupFormOpen = $showForm || $editGroup;
$groupById = [];
foreach ($groups as $g) {
    $groupById[(int) $g['id']] = $g;
}
?>
<section class="provider-panel">
    <div class="panel-toolbar">
        <div>
            <h3>Grupos</h3>
            <p class="muted" style="margin:0.25rem 0 0">
                Subconjuntos opcionales de certificaciones (p. ej. ITEP, UKS).
                Cada certificación pertenece a <strong>un solo grupo</strong> o a ninguno.
                Para documentos/links de <em>toda la empresa</em>, usa alcance «Empresa» — no hace falta un grupo «General».
            </p>
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
                <label>Nombre<input name="name" required value="<?= e($editGroup['name'] ?? '') ?>" placeholder="Ej. ITEP, UKS, TOEFL"></label>
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
    <?php else: ?>
        <p class="muted" style="margin-top:1rem">Sin grupos. Créalos solo si necesitas alcance parcial (documentos/links de un subconjunto de certificaciones).</p>
    <?php endif; ?>

    <?php if ($certifications): ?>
        <div class="inline-form-panel" style="margin-top:1.25rem">
            <h4 style="margin:0 0 0.35rem">Asignar certificaciones</h4>
            <p class="muted" style="margin:0 0 0.75rem">
                Elige el grupo de cada certificación. «Sin grupo» = solo aplica lo de alcance empresa o la certificación misma.
            </p>
            <form method="post" action="/admin/providers/group/assign-all" class="form-grid">
                <input type="hidden" name="provider_id" value="<?= $id ?>">
                <div class="table-wrap field-wide">
                    <table class="data-table">
                        <thead><tr><th>Certificación</th><th>Grupo</th></tr></thead>
                        <tbody>
                        <?php foreach ($certifications as $c): ?>
                            <?php $currentGid = (int) ($c['provider_group_id'] ?? 0); ?>
                            <tr>
                                <td>
                                    <strong><?= e($c['name']) ?></strong>
                                    <code class="muted"><?= e($c['code']) ?></code>
                                </td>
                                <td>
                                    <select name="cert_group[<?= (int)$c['id'] ?>]">
                                        <option value="0" <?= $currentGid === 0 ? 'selected' : '' ?>>Sin grupo</option>
                                        <?php foreach ($groups as $g): ?>
                                            <option value="<?= (int)$g['id'] ?>" <?= $currentGid === (int)$g['id'] ? 'selected' : '' ?>>
                                                <?= e($g['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="actions">
                    <button class="btn" type="submit">Guardar asignaciones</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</section>

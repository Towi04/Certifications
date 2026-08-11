<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$partnersTab = 'niveles';
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <h2 style="margin:0"><?= e($title) ?></h2>
        <a class="btn btn-ghost" href="/admin/partners?tab=niveles">Volver a niveles</a>
    </div>
    <?php require __DIR__ . '/../partners/_tabs.php'; ?>
    <form method="post" action="/admin/tiers/save" class="stack form-grid">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
        <label>Código<input name="code" required value="<?= e($item['code'] ?? '') ?>"></label>
        <label>Nombre<input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
        <label>Orden<input type="number" name="sort_order" value="<?= e((string)($item['sort_order'] ?? '0')) ?>"></label>
        <label>Descripción<textarea name="description" rows="4"><?= e($item['description'] ?? '') ?></textarea></label>
        <label class="check"><input type="checkbox" name="is_active" <?= !isset($item) || (int)($item['is_active'] ?? 1) ? 'checked' : '' ?>> Activo</label>
        <div class="actions">
            <button class="btn" type="submit">Guardar</button>
            <a class="btn btn-ghost" href="/admin/partners?tab=niveles">Cancelar</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../_nav.php'; $item = $item ?? null; ?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <form method="post" action="/admin/providers/save" class="stack form-grid">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
        <label>Código<input name="code" required value="<?= e($item['code'] ?? '') ?>"></label>
        <label>Nombre<input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
        <label>Sitio web<input name="website_url" value="<?= e($item['website_url'] ?? '') ?>"></label>
        <label>Notas<textarea name="notes" rows="4"><?= e($item['notes'] ?? '') ?></textarea></label>
        <label class="check"><input type="checkbox" name="is_active" <?= !isset($item) || (int)($item['is_active'] ?? 1) ? 'checked' : '' ?>> Activo</label>
        <div class="actions"><button class="btn" type="submit">Guardar</button><a class="btn btn-ghost" href="/admin/providers">Volver</a></div>
    </form>
</section>

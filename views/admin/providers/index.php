<?php require __DIR__ . '/../_nav.php'; ?>
<section class="providers-page">
    <div class="page-head">
        <div>
            <h1 style="margin:0">Proveedores</h1>
            <p class="muted" style="margin:0.35rem 0 0">Casas certificadoras, contacto, sedes y convenios</p>
        </div>
        <a class="btn" href="/admin/providers/create">Nuevo proveedor</a>
    </div>

    <div class="providers-grid">
        <?php foreach ($items as $item): ?>
            <?php
            $icon = $item['logo_icon_path'] ?? $item['logo_path'] ?? null;
            $active = (int) $item['is_active'] === 1;
            ?>
            <article class="provider-tile <?= $active ? '' : 'is-inactive' ?>">
                <a class="provider-tile-main" href="/admin/providers/edit?id=<?= (int)$item['id'] ?>">
                    <span class="provider-tile-icon">
                        <?php if ($icon): ?>
                            <img src="/media?f=<?= e(rawurlencode($icon)) ?>" alt="" width="56" height="56" style="width:56px;height:56px;object-fit:contain;display:block">
                        <?php else: ?>
                            <span class="provider-tile-fallback"><?= e(mb_substr($item['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="provider-tile-body">
                        <strong><?= e($item['name']) ?></strong>
                        <small><code><?= e($item['code']) ?></code> · <?= (int)($item['certifications_count'] ?? 0) ?> certificaciones</small>
                    </span>
                </a>
                <form method="post" action="/admin/providers/toggle-active" class="provider-tile-eye"
                      onsubmit="return confirm(<?= json_encode('¿Seguro que quieres ' . ($active ? 'desactivar' : 'activar') . ' a ' . $item['name'] . '?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);">
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                    <button type="submit" class="eye-btn" title="<?= $active ? 'Desactivar' : 'Activar' ?>" aria-label="<?= $active ? 'Desactivar' : 'Activar' ?>">
                        <?php if ($active): ?>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.8"/></svg>
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 3l18 18M10.5 10.6A3.2 3.2 0 0 0 13.4 13.5M9.9 5.2C10.6 5.1 11.3 5 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-4.2 4.8M6.1 6.1A17.4 17.4 0 0 0 2 12s3.5 7 10 7c1.3 0 2.5-.3 3.6-.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        <?php endif; ?>
                    </button>
                </form>
            </article>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <p class="muted">No hay proveedores. Crea el primero.</p>
        <?php endif; ?>
    </div>
</section>

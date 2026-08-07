<?php require __DIR__ . '/../_nav.php'; ?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Proveedores</h2>
            <p class="muted" style="margin:0.35rem 0 0">Casas certificadoras, contacto y convenios</p>
        </div>
        <a class="btn" href="/admin/providers/create">Nuevo proveedor</a>
    </div>

    <div class="provider-list">
        <?php foreach ($items as $item): ?>
            <a class="provider-list-card" href="/admin/providers/edit?id=<?= (int)$item['id'] ?>">
                <span class="provider-list-logo">
                    <?php if (!empty($item['logo_path'])): ?>
                        <img src="/media?f=<?= e(rawurlencode($item['logo_path'])) ?>" alt="">
                    <?php else: ?>
                        <span class="provider-list-fallback"><?= e(mb_substr($item['name'], 0, 1)) ?></span>
                    <?php endif; ?>
                </span>
                <span class="provider-list-body">
                    <strong><?= e($item['name']) ?></strong>
                    <small>
                        <code><?= e($item['code']) ?></code>
                        · <?= (int)($item['certifications_count'] ?? 0) ?> certificaciones
                        <?php if (!empty($item['contact_name'])): ?>
                            · <?= e($item['contact_name']) ?>
                        <?php endif; ?>
                    </small>
                </span>
                <span class="provider-list-status <?= (int)$item['is_active'] ? 'on' : 'off' ?>">
                    <?= (int)$item['is_active'] ? 'Activo' : 'Inactivo' ?>
                </span>
            </a>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <p class="muted">No hay proveedores. Crea el primero.</p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../_nav.php'; ?>
<section class="note">
    <div class="page-head" style="margin:0">
        <h2 style="margin:0">Certificaciones</h2>
        <a class="btn" href="/admin/certifications/create">Nueva</a>
    </div>
    <form method="get" class="filters stack form-grid" style="margin-top:1rem">
        <label>Buscar<input name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="nombre o código"></label>
        <label>Proveedor
            <select name="provider_id">
                <option value="">Todos</option>
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (string)($filters['provider_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Publicada
            <select name="is_published">
                <option value="">Todas</option>
                <option value="1" <?= ($filters['is_published'] ?? '') === '1' ? 'selected' : '' ?>>Sí</option>
                <option value="0" <?= ($filters['is_published'] ?? '') === '0' ? 'selected' : '' ?>>No</option>
            </select>
        </label>
        <button class="btn" type="submit">Filtrar</button>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Código</th><th>Nombre</th><th>Proveedor</th><th>Precio público</th><th>Publicada</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><code><?= e($item['code']) ?></code></td>
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($item['provider_name']) ?></td>
                    <td><?= e(\App\Support\Str::money(isset($item['public_price']) ? (float)$item['public_price'] : null, $item['currency'] ?? 'MXN')) ?></td>
                    <td><?= (int)$item['is_published'] ? 'Sí' : 'No' ?></td>
                    <td><a href="/admin/certifications/edit?id=<?= (int)$item['id'] ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

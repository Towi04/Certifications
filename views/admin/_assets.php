<?php
/** @var list<array<string,mixed>> $assets */
/** @var list<string> $assetTypes */
/** @var string $ownerType */
/** @var int $ownerId */
/** @var string $redirect */
$assets = $assets ?? [];
$assetTypes = $assetTypes ?? ['other'];
?>
<section class="note assets-panel">
    <h3>Assets / archivos</h3>
    <?php if ($assets): ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Tipo</th><th>Título</th><th>Archivo</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($assets as $asset): ?>
                    <tr>
                        <td><code><?= e($asset['asset_type']) ?></code></td>
                        <td><?= e($asset['title'] ?? '—') ?></td>
                        <td><a href="/media?f=<?= e(rawurlencode($asset['file_path'])) ?>" target="_blank" rel="noopener">Ver</a></td>
                        <td>
                            <form method="post" action="/admin/assets/delete" class="inline-form">
                                <input type="hidden" name="asset_id" value="<?= (int)$asset['id'] ?>">
                                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                                <button type="submit" class="linkish">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="muted">Sin archivos aún.</p>
    <?php endif; ?>

    <form method="post" action="/admin/assets/upload" enctype="multipart/form-data" class="stack form-grid" style="margin-top:1rem">
        <input type="hidden" name="owner_type" value="<?= e($ownerType) ?>">
        <input type="hidden" name="owner_id" value="<?= (int)$ownerId ?>">
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
        <label>Tipo
            <select name="asset_type" required>
                <?php foreach ($assetTypes as $type): ?>
                    <option value="<?= e($type) ?>"><?= e($type) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Título<input name="title" placeholder="Opcional"></label>
        <label>Archivo<input type="file" name="file" required accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.svg,image/*,application/pdf"></label>
        <div class="actions"><button class="btn" type="submit">Subir</button></div>
    </form>
</section>

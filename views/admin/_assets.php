<?php
/** @var list<array<string,mixed>> $assets */
/** @var list<string> $assetTypes */
/** @var string $ownerType */
/** @var int $ownerId */
/** @var string $redirect */
$assets = $assets ?? [];
$assetTypes = $assetTypes ?? ['other'];
$hasYoutube = in_array('youtube', $assetTypes, true);
?>
<section class="note assets-panel">
    <h3>Assets / archivos</h3>
    <p class="muted">Imágenes, logo, PDFs y videos de YouTube (se incrustan en la ficha pública).</p>
    <?php if ($assets): ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Tipo</th><th>Título</th><th>Archivo / enlace</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($assets as $asset): ?>
                    <?php
                    $isYt = \App\Support\ProductAssetView::isYoutube($asset);
                    $href = \App\Support\ProductAssetView::mediaHref($asset);
                    ?>
                    <tr>
                        <td><?= e(\App\Support\ProductAssetView::typeLabel((string) $asset['asset_type'])) ?></td>
                        <td><?= e($asset['title'] ?? '—') ?></td>
                        <td>
                            <a href="<?= e($href) ?>" target="_blank" rel="noopener">
                                <?= $isYt ? 'Ver en YouTube' : 'Ver' ?>
                            </a>
                        </td>
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

    <form method="post" action="/admin/assets/upload" enctype="multipart/form-data" class="stack form-grid" style="margin-top:1rem" id="assetUploadForm">
        <input type="hidden" name="owner_type" value="<?= e($ownerType) ?>">
        <input type="hidden" name="owner_id" value="<?= (int)$ownerId ?>">
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
        <label>Tipo
            <select name="asset_type" id="assetTypeSelect" required>
                <?php foreach ($assetTypes as $type): ?>
                    <option value="<?= e($type) ?>"><?= e(\App\Support\ProductAssetView::typeLabel($type)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Título<input name="title" placeholder="Opcional"></label>
        <label id="assetFileField">Archivo
            <input type="file" name="file" id="assetFileInput" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.svg,image/*,application/pdf">
        </label>
        <?php if ($hasYoutube): ?>
            <label id="assetYoutubeField" hidden>Enlace de YouTube
                <input type="url" name="youtube_url" id="assetYoutubeInput" placeholder="https://www.youtube.com/watch?v=…">
                <span class="muted" style="font-weight:400;display:block;margin-top:0.25rem">Solo enlaces de YouTube; se embebe en la ficha.</span>
            </label>
        <?php endif; ?>
        <div class="actions"><button class="btn" type="submit">Agregar</button></div>
    </form>
</section>
<?php if ($hasYoutube): ?>
<script>
(function () {
  var sel = document.getElementById('assetTypeSelect');
  var fileField = document.getElementById('assetFileField');
  var fileInput = document.getElementById('assetFileInput');
  var ytField = document.getElementById('assetYoutubeField');
  var ytInput = document.getElementById('assetYoutubeInput');
  if (!sel || !fileField || !ytField) return;
  function sync() {
    var isYt = sel.value === 'youtube';
    fileField.hidden = isYt;
    ytField.hidden = !isYt;
    if (fileInput) {
      fileInput.required = !isYt;
      if (isYt) fileInput.value = '';
    }
    if (ytInput) {
      ytInput.required = isYt;
      if (!isYt) ytInput.value = '';
    }
  }
  sel.addEventListener('change', sync);
  sync();
})();
</script>
<?php endif; ?>

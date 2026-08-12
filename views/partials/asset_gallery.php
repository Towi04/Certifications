<?php
/**
 * Galería de visuales con lightbox flotante (imágenes + YouTube).
 *
 * @var list<array<string,mixed>> $galleryAssets
 * @var string|null $galleryTitle
 */
use App\Support\ProductAssetView;

$galleryAssets = array_values(array_filter(
    $galleryAssets ?? [],
    static fn ($a) => ProductAssetView::isLightboxable($a) || ProductAssetView::isPdf($a)
));
$galleryTitle = $galleryTitle ?? 'Visuales';
if ($galleryAssets === []) {
    return;
}

$lightboxItems = [];
foreach ($galleryAssets as $a) {
    if (ProductAssetView::isLightboxable($a)) {
        $lightboxItems[] = ProductAssetView::lightboxPayload($a);
    }
}
$lightboxJson = htmlspecialchars(
    json_encode($lightboxItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
    ENT_QUOTES,
    'UTF-8'
);
$lightboxIndex = 0;
?>
<h3><?= e($galleryTitle) ?></h3>
<div class="asset-gallery" data-asset-gallery data-lightbox-items="<?= $lightboxJson ?>">
    <?php foreach ($galleryAssets as $a): ?>
        <?php
        $title = trim((string) ($a['title'] ?? '')) ?: ProductAssetView::typeLabel((string) ($a['asset_type'] ?? 'other'));
        $thumb = ProductAssetView::thumbSrc($a);
        $isLb = ProductAssetView::isLightboxable($a);
        $isYt = ProductAssetView::isYoutube($a);
        ?>
        <?php if ($isLb): ?>
            <button type="button"
                    class="asset-thumb<?= $isYt ? ' asset-thumb--video' : '' ?>"
                    data-lightbox-open="<?= (int) $lightboxIndex ?>"
                    aria-label="<?= e('Abrir: ' . $title) ?>">
                <?php if ($thumb): ?>
                    <img src="<?= e($thumb) ?>" alt="">
                <?php else: ?>
                    <span><?= e($title) ?></span>
                <?php endif; ?>
                <?php if ($isYt): ?><span class="asset-thumb-play" aria-hidden="true">▶</span><?php endif; ?>
            </button>
            <?php $lightboxIndex++; ?>
        <?php else: ?>
            <a class="asset-thumb" href="<?= e(ProductAssetView::mediaHref($a)) ?>" target="_blank" rel="noopener">
                <span><?= e($title) ?></span>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php if ($lightboxItems !== []): ?>
<div class="asset-lightbox" id="assetLightbox" hidden>
    <div class="asset-lightbox__backdrop" data-lightbox-close></div>
    <div class="asset-lightbox__dialog" role="dialog" aria-modal="true" aria-label="Visor de visuales">
        <button type="button" class="asset-lightbox__close" data-lightbox-close aria-label="Cerrar">×</button>
        <button type="button" class="asset-lightbox__nav asset-lightbox__nav--prev" data-lightbox-prev aria-label="Anterior">‹</button>
        <button type="button" class="asset-lightbox__nav asset-lightbox__nav--next" data-lightbox-next aria-label="Siguiente">›</button>
        <div class="asset-lightbox__stage">
            <img class="asset-lightbox__image" alt="" hidden>
            <iframe class="asset-lightbox__video" title="Video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen hidden></iframe>
        </div>
        <p class="asset-lightbox__caption"></p>
        <p class="asset-lightbox__counter muted"></p>
    </div>
</div>
<?php endif; ?>

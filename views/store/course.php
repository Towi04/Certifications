<?php
$item = $item ?? [];
$assets = $assets ?? [];
$findAsset = static function (array $list, string $type): ?array {
    foreach ($list as $a) {
        if (($a['asset_type'] ?? '') === $type) {
            return $a;
        }
    }

    return null;
};
$logo = $findAsset($assets, 'course_logo') ?? $findAsset($assets, 'cover');
$samples = array_values(array_filter(
    $assets,
    static fn ($a) => in_array($a['asset_type'] ?? '', ['course_logo', 'cover', 'other', 'youtube'], true)
        && (\App\Support\ProductAssetView::isLightboxable($a) || \App\Support\ProductAssetView::isPdf($a))
));
$youtubeAssets = array_values(array_filter(
    $assets,
    static fn ($a) => \App\Support\ProductAssetView::isYoutube($a)
));
$platformRaw = strtolower(trim((string) ($item['platform_type'] ?? '')));
$platformLabel = match ($platformRaw) {
    'moodle' => 'Campus',
    'ethinking' => 'eThinking',
    'xperienceed' => 'XperienceEd',
    '' => '',
    default => (string) ($item['platform_type'] ?? ''),
};
$canBuy = (int) ($item['is_published'] ?? 0) === 1
    && $item['public_price'] !== null
    && trim((string) ($item['slug'] ?? '')) !== '';
?>
<section class="page-head">
    <div>
        <?php if ($platformLabel !== ''): ?><p class="eyebrow"><?= e($platformLabel) ?></p><?php endif; ?>
        <h1><?= e($item['name'] ?? 'Curso') ?></h1>
        <?php if ($item['public_price'] !== null): ?>
            <p class="price" style="margin:0.35rem 0 0">
                <?= e(\App\Support\Str::money((float) $item['public_price'], $item['currency'] ?? 'MXN')) ?>
            </p>
        <?php endif; ?>
    </div>
    <div class="actions">
        <?php if ($canBuy): ?>
            <a class="btn" href="/adquirir-curso?slug=<?= e(rawurlencode((string) $item['slug'])) ?>">Adquirir</a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="/#cursos">Volver a cursos</a>
    </div>
</section>

<div class="product-layout">
    <div class="product-main note">
        <?php if ($logo): ?>
            <div class="value-block-logo" style="margin-bottom:1rem">
                <img src="/media?f=<?= e(rawurlencode((string) $logo['file_path'])) ?>" alt="<?= e($item['name'] ?? '') ?>">
            </div>
        <?php endif; ?>
        <?php if (!empty($item['description'])): ?>
            <div class="prose"><p><?= nl2br(e((string) $item['description'])) ?></p></div>
        <?php elseif (!empty($item['access_notes'])): ?>
            <div class="prose"><p><?= nl2br(e((string) $item['access_notes'])) ?></p></div>
        <?php else: ?>
            <p class="muted">Pronto publicaremos más detalles de este curso.</p>
        <?php endif; ?>
    </div>
    <aside class="product-side note">
        <?php if ($samples): ?>
            <?php
            $galleryAssets = $samples;
            $galleryTitle = 'Visuales';
            require __DIR__ . '/../partials/asset_gallery.php';
            ?>
        <?php endif; ?>
        <?php if ($youtubeAssets): ?>
            <h3>Videos</h3>
            <div class="asset-youtube-embeds">
                <?php foreach ($youtubeAssets as $yt): ?>
                    <?php $embed = \App\Support\YoutubeUrl::embedUrl((string) ($yt['file_path'] ?? '')); ?>
                    <?php if ($embed): ?>
                        <div class="asset-youtube-embed">
                            <?php if (!empty($yt['title'])): ?>
                                <p class="muted"><?= e((string) $yt['title']) ?></p>
                            <?php endif; ?>
                            <iframe src="<?= e($embed) ?>" title="<?= e((string) ($yt['title'] ?? 'Video')) ?>"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    allowfullscreen loading="lazy"></iframe>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </aside>
</div>
<script src="/assets/js/asset-lightbox.js?v=<?= e((string) (@filemtime(BASE_PATH . '/public/assets/js/asset-lightbox.js') ?: time())) ?>" defer></script>

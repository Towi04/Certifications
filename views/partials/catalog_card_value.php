<?php
/**
 * Fragmento de ficha de catálogo: logo + puntos de valor (sin resumen).
 * @var array $item
 * @var int $maxPoints
 */
$maxPoints = $maxPoints ?? 4;
$points = \App\Catalog\CatalogRepository::decodeValuePoints($item['value_points_json'] ?? null);
if ($maxPoints > 0 && count($points) > $maxPoints) {
    $points = array_slice($points, 0, $maxPoints);
}
$logo = $item['exam_logo_path'] ?? null;
?>
<?php if (!empty($logo)): ?>
    <div class="catalog-card-logo">
        <img src="/media?f=<?= e(rawurlencode((string)$logo)) ?>" alt="" loading="lazy">
    </div>
<?php endif; ?>
<?php if ($points): ?>
    <ul class="catalog-value-list">
        <?php foreach ($points as $point): ?>
            <li><?= e($point) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

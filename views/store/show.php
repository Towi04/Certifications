<?php
$item = $item ?? [];
$protocolSteps = $protocolSteps ?? [];
$courses = $courses ?? [];
$assets = $assets ?? [];
$phaseLabels = [
    'pre_exam' => 'Pre-examen',
    'during_exam' => 'Durante el examen',
    'post_exam' => 'Post-examen',
];
$respLabels = [
    'student' => 'Alumno',
    'admin' => 'Administrador',
    'tr' => 'TR',
    'student_or_tr' => 'Alumno o TR',
    'provider' => 'Certificadora',
    'sep' => 'SEP',
    'system' => 'Sistema',
];
$findAsset = static function (array $list, string $type): ?array {
    foreach ($list as $a) {
        if (($a['asset_type'] ?? '') === $type) {
            return $a;
        }
    }
    return null;
};
$examLogo = $findAsset($assets, 'exam_logo') ?? $findAsset($assets, 'badge') ?? $findAsset($assets, 'cover');
$samples = array_values(array_filter($assets, static fn ($a) => in_array($a['asset_type'], ['certificate_sample', 'badge', 'cover', 'exam_logo'], true)));
$valuePoints = \App\Catalog\CatalogRepository::decodeValuePoints($item['value_points_json'] ?? null);
?>
<section class="page-head">
    <div>
        <p class="eyebrow"><?= e($item['provider_name'] ?? '') ?></p>
        <h1><?= e($item['name']) ?></h1>
    </div>
    <div class="actions">
        <a class="btn btn-ghost" href="/#catalogo">Volver al catálogo</a>
        <a class="btn" href="/adquirir?slug=<?= e(rawurlencode($item['slug'])) ?>">Adquirir</a>
    </div>
</section>

<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (!empty($info)): ?><div class="alert alert-ok"><?= e($info) ?></div><?php endif; ?>

<div class="product-layout">
    <div class="product-main note">
        <div class="value-block value-block--hero">
            <?php if ($examLogo): ?>
                <div class="value-block-logo">
                    <img src="/media?f=<?= e(rawurlencode((string)$examLogo['file_path'])) ?>" alt="<?= e($examLogo['title'] ?? $item['name']) ?>">
                </div>
            <?php endif; ?>
            <?php if ($valuePoints): ?>
                <h2>Por qué con Instituto Doceo</h2>
                <ul class="value-list">
                    <?php foreach ($valuePoints as $point): ?>
                        <li><?= e($point) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php elseif (!$examLogo): ?>
                <p class="muted">Pronto publicaremos los puntos clave de esta certificación.</p>
            <?php endif; ?>
        </div>

        <p class="price price-lg">
            <?php if ($item['public_price'] !== null): ?>
                <?= e(\App\Support\Str::money((float)$item['public_price'], $item['currency'] ?? 'MXN')) ?>
            <?php else: ?>
                Precio a consultar
            <?php endif; ?>
        </p>
        <ul class="facts">
            <li><strong>Modalidad:</strong> <?= e($item['modality'] ?? '—') ?></li>
            <?php if (!empty($item['duration_label'])): ?><li><strong>Duración:</strong> <?= e($item['duration_label']) ?></li><?php endif; ?>
            <?php if (!empty($item['audience'])): ?><li><strong>Dirigido a:</strong> <?= e($item['audience']) ?></li><?php endif; ?>
            <?php if ((int)$item['cenni_eligible']): ?><li><strong>CENNI:</strong> elegible</li><?php endif; ?>
            <?php if ((int)$item['conocer_eligible']): ?><li><strong>CONOCER:</strong> elegible</li><?php endif; ?>
        </ul>

        <?php if (!empty($item['description_html'])): ?>
            <h2>Descripción</h2>
            <div class="prose"><?= $item['description_html'] ?></div>
        <?php endif; ?>

        <?php if ($protocolSteps): ?>
            <h2>Cómo se aplica</h2>
            <ol class="protocol-timeline">
                <?php
                $lastPhase = null;
                foreach ($protocolSteps as $step):
                    $phase = (string) ($step['phase'] ?? '');
                    if ($phase !== $lastPhase):
                        $lastPhase = $phase;
                ?>
                    <li class="protocol-phase-label"><?= e($phaseLabels[$phase] ?? $phase) ?></li>
                <?php endif; ?>
                    <li class="protocol-step">
                        <div class="protocol-step-head">
                            <span class="protocol-step-num"><?= (int)$step['sort_order'] ?></span>
                            <strong><?= e($step['title']) ?></strong>
                            <span class="pill"><?= e($respLabels[$step['responsible']] ?? $step['responsible']) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>

    <aside class="product-side note">
        <a class="btn" style="width:100%;text-align:center" href="/adquirir?slug=<?= e(rawurlencode($item['slug'])) ?>">Adquirir</a>
        <p class="muted" style="margin-top:0.75rem">
            No necesitas cuenta previa. Al adquirir creas tu acceso de alumno para dar seguimiento.
        </p>
        <?php if ($samples): ?>
            <h3>Visuales</h3>
            <div class="asset-gallery">
                <?php foreach ($samples as $a): ?>
                    <a class="asset-thumb" href="/media?f=<?= e(rawurlencode($a['file_path'])) ?>" target="_blank" rel="noopener">
                        <?php if (preg_match('/\.(jpe?g|png|gif|webp)$/i', (string)$a['file_path'])): ?>
                            <img src="/media?f=<?= e(rawurlencode($a['file_path'])) ?>" alt="">
                        <?php else: ?>
                            <span><?= e($a['title'] ?: $a['asset_type']) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($courses): ?>
            <h3>Cursos relacionados</h3>
            <ul class="facts">
                <?php foreach ($courses as $c): ?>
                    <li><?= e($c['course_name']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </aside>
</div>

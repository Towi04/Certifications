<?php
/** @var array $item */
/** @var array|null $partnerPrice */
/** @var array $courses */
/** @var array $assets */
/** @var array $providerAssets */
$assets = $assets ?? [];
$providerAssets = $providerAssets ?? [];

$findAsset = static function (array $list, string $type): ?array {
    foreach ($list as $a) {
        if (($a['asset_type'] ?? '') === $type) {
            return $a;
        }
    }
    return null;
};

$cover = $findAsset($assets, 'cover') ?? $findAsset($assets, 'exam_logo');
$providerLogo = $findAsset($providerAssets, 'provider_logo');
$samples = array_values(array_filter($assets, static fn ($a) => in_array($a['asset_type'], ['certificate_sample', 'badge', 'exam_logo', 'cover'], true)));
$docs = array_values(array_filter($assets, static fn ($a) => in_array($a['asset_type'], ['syllabus_pdf', 'regulation_pdf'], true)));
?>
<section class="page-head">
    <div>
        <p class="eyebrow"><?= e($item['provider_name']) ?></p>
        <h1><?= e($item['name']) ?></h1>
        <p class="muted"><?php if (!empty($item['short_description'])): ?><span class="prose prose-inline"><?= $item['short_description'] ?></span><?php endif; ?></p>
    </div>
    <div class="actions">
        <a class="btn btn-ghost" href="/partner">Volver al catálogo</a>
    </div>
</section>

<?php if ($cover || $providerLogo): ?>
<section class="sheet-visual">
    <?php if ($cover): ?>
        <img class="sheet-cover" src="/media?f=<?= e(rawurlencode($cover['file_path'])) ?>" alt="<?= e($cover['title'] ?? $item['name']) ?>">
    <?php endif; ?>
    <?php if ($providerLogo): ?>
        <img class="sheet-logo" src="/media?f=<?= e(rawurlencode($providerLogo['file_path'])) ?>" alt="<?= e($item['provider_name']) ?>">
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="note product-sheet">
    <div class="price-box">
        <?php if ($partnerPrice): ?>
            <p class="eyebrow">Tu precio de convenio</p>
            <p class="price-lg"><?= e(\App\Support\Str::money((float)$partnerPrice['price'], $partnerPrice['currency'])) ?></p>
            <?php if ($partner): ?>
                <p class="muted"><?= e($partner['agreement_name'] ?? $partner['tier_name'] ?? '') ?></p>
            <?php endif; ?>
        <?php else: ?>
            <p class="eyebrow">Precio partner</p>
            <p class="muted">Aún no hay precio cargado para tu convenio. Contacta a Doceo.</p>
        <?php endif; ?>
        <?php if ($item['public_price'] !== null): ?>
            <p class="muted">Referencia público: <?= e(\App\Support\Str::money((float)$item['public_price'], $item['currency'])) ?></p>
        <?php endif; ?>
    </div>

    <div class="sheet-grid">
        <div>
            <h2>Ficha</h2>
            <ul class="facts">
                <li><strong>Modalidad:</strong> <?= e(\App\Catalog\CatalogRepository::modalities()[$item['modality']] ?? ucfirst((string)$item['modality'])) ?></li>
                <li><strong>Duración:</strong> <?= e($item['duration_label'] ?? '—') ?></li>
                <li><strong>Audiencia:</strong> <?= e($item['audience'] ?? '—') ?></li>
                <li><strong>Rango:</strong> <?= e($item['score_range'] ?? '—') ?></li>
                <?php if (!empty($item['provider_brand_website'])): ?>
                    <li><strong>Sitio oficial:</strong>
                        <a href="<?= e($item['provider_brand_website']) ?>" target="_blank" rel="noopener">
                            <?= e(preg_replace('#^https?://#i', '', rtrim((string)$item['provider_brand_website'], '/'))) ?>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (!empty($item['is_level_exam'])): ?>
                    <?php
                    $skills = [];
                    if (!empty($item['skills_json'])) {
                        $decoded = is_string($item['skills_json']) ? json_decode($item['skills_json'], true) : $item['skills_json'];
                        if (is_array($decoded)) {
                            $catalog = \App\Catalog\CatalogRepository::certificationSkills();
                            foreach ($decoded as $sk) {
                                $skills[] = $catalog[$sk] ?? $sk;
                            }
                        }
                    }
                    ?>
                    <li><strong>Habilidades:</strong> <?= $skills ? e(implode(', ', $skills)) : '—' ?></li>
                <?php endif; ?>
                <li><strong>Protocolo:</strong> <?= e($item['protocol_name'] ?? '—') ?></li>
            </ul>
        </div>
        <div>
            <h2>Trámites SEP</h2>
            <ul class="facts">
                <li><strong>CENNI:</strong>
                    <?php if ((int)$item['cenni_eligible']): ?>
                        <?php
                        $cenniLabels = \App\Catalog\CatalogRepository::cenniDocTypes();
                        $docLabel = $cenniLabels[$item['cenni_doc_type']] ?? $item['cenni_doc_type'];
                        $fee = isset($item['cenni_fee']) ? (float) $item['cenni_fee'] : 0.0;
                        $included = $fee <= 0 || (int) ($item['cenni_included'] ?? 0) === 1;
                        ?>
                        Sí · <?= e((string)$docLabel) ?>
                        <?= $included ? ' · incluido' : ' · ' . e(\App\Support\Str::money($fee)) ?>
                    <?php else: ?>
                        No elegible
                    <?php endif; ?>
                </li>
                <li><strong>CONOCER:</strong>
                    <?php if ((int)$item['conocer_eligible']): ?>
                        Sí · <?= e(\App\Support\Str::money(isset($item['conocer_fee']) ? (float)$item['conocer_fee'] : null)) ?> (aparte)
                    <?php else: ?>
                        No elegible
                    <?php endif; ?>
                </li>
            </ul>
        </div>
    </div>

    <?php if ($samples): ?>
        <h2>Visuales</h2>
        <div class="asset-gallery">
            <?php foreach ($samples as $a): ?>
                <a class="asset-thumb" href="/media?f=<?= e(rawurlencode($a['file_path'])) ?>" target="_blank" rel="noopener">
                    <?php if (str_ends_with(strtolower((string)$a['file_path']), '.pdf')): ?>
                        <span><?= e($a['title'] ?: $a['asset_type']) ?></span>
                    <?php else: ?>
                        <img src="/media?f=<?= e(rawurlencode($a['file_path'])) ?>" alt="<?= e($a['title'] ?? $a['asset_type']) ?>">
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($docs): ?>
        <h2>Documentos</h2>
        <ul class="facts">
            <?php foreach ($docs as $a): ?>
                <li>
                    <a href="/media?f=<?= e(rawurlencode($a['file_path'])) ?>" target="_blank" rel="noopener">
                        <?= e($a['title'] ?: $a['asset_type']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($item['description_html'])): ?>
        <h2>Descripción</h2>
        <div class="prose"><?= $item['description_html'] ?></div>
    <?php endif; ?>

    <?php if (!empty($item['syllabus_html'])): ?>
        <h2>Temario</h2>
        <div class="prose"><?= $item['syllabus_html'] ?></div>
    <?php endif; ?>

    <h2>Cómo se aplica</h2>
    <?php if (!empty($item['protocol_procedure_html'])): ?>
        <div class="prose"><?= $item['protocol_procedure_html'] ?></div>
    <?php else: ?>
        <p class="muted">Sin protocolo detallado aún.</p>
    <?php endif; ?>
    <ul class="facts">
        <?php if (!empty($item['requires_regulation_signature'])): ?><li>Requiere firma de reglamento</li><?php endif; ?>
        <?php if (!empty($item['requires_software'])): ?><li>Requiere instalar software</li><?php endif; ?>
        <?php if (!empty($item['requires_zoom'])): ?><li>Incluye enlace Zoom</li><?php endif; ?>
        <?php if (!empty($item['requires_vm'])): ?><li>Examen en máquina virtual</li><?php endif; ?>
        <?php if (!empty($item['uses_inventory'])): ?><li>Se asigna desde inventario (no se compra unitario al proveedor)</li><?php endif; ?>
    </ul>

    <h2>Cursos relacionados</h2>
    <?php if ($courses): ?>
        <ul class="facts">
            <?php foreach ($courses as $c): ?>
                <li>
                    <strong><?= e($c['course_name']) ?></strong>
                    · <?= e($c['relation_type']) ?>
                    · <?= e($c['platform_type']) ?>
                    <?php if (!empty($c['external_url'])): ?>
                        · <a href="<?= e($c['external_url']) ?>" target="_blank" rel="noopener">abrir</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="muted">Sin cursos vinculados.</p>
    <?php endif; ?>
</section>

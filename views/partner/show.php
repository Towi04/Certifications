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
    </div>
    <div class="actions">
        <a class="btn btn-ghost" href="/partner">Volver al catálogo</a>
        <a class="btn btn-ghost" href="/partner/convenio">Mi convenio</a>
    </div>
</section>

<?php if (isset($canRegister) && !$canRegister): ?>
<section class="note">
    <p>
        Registro de alumnos bloqueado hasta confirmar tu convenio firmado.
        <a href="/partner/convenio">Subir / ver convenio</a>
    </p>
</section>
<?php endif; ?>

<?php
$valuePoints = \App\Catalog\CatalogRepository::decodeValuePoints($item['value_points_json'] ?? null);
$examLogoAsset = $findAsset($assets, 'exam_logo') ?? $findAsset($assets, 'badge') ?? $cover;
?>
<?php if ($examLogoAsset || $valuePoints): ?>
<section class="note value-block value-block--hero">
    <?php if ($examLogoAsset): ?>
        <div class="value-block-logo">
            <img src="/media?f=<?= e(rawurlencode((string)$examLogoAsset['file_path'])) ?>" alt="<?= e($examLogoAsset['title'] ?? $item['name']) ?>">
        </div>
    <?php endif; ?>
    <?php if ($valuePoints): ?>
        <h2>Por qué con Instituto Doceo</h2>
        <ul class="value-list">
            <?php foreach ($valuePoints as $point): ?>
                <li><?= e($point) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($cover || $providerLogo): ?>
<section class="sheet-visual">
    <?php if ($cover && (!$examLogoAsset || ($cover['file_path'] ?? '') !== ($examLogoAsset['file_path'] ?? ''))): ?>
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
            <p class="eyebrow">Tu precio Teacher Referral</p>
            <p class="price-lg"><?= e(\App\Support\Str::money((float)$partnerPrice['price'], $partnerPrice['currency'] ?? 'MXN')) ?></p>
            <?php if ($partner): ?>
                <p class="muted"><?= e($partner['tier_name'] ?? $partner['agreement_name'] ?? '') ?></p>
            <?php endif; ?>
        <?php else: ?>
            <p class="eyebrow">Precio partner</p>
            <p class="muted">Aún no hay precio cargado para tu nivel. Contacta a Doceo.</p>
        <?php endif; ?>
        <?php if ($item['public_price'] !== null): ?>
            <p class="muted">Referencia público: <?= e(\App\Support\Str::money((float)$item['public_price'], $item['currency'] ?? 'MXN')) ?></p>
        <?php endif; ?>
    </div>

    <div class="sheet-grid">
        <div>
            <h2>Ficha</h2>
            <ul class="facts">
                <li><strong>Modalidad:</strong>
                    <?= \App\Support\CertIcons::modalityHtml((string)($item['modality'] ?? ''), 'cert-meta-icons--lg') ?>
                    <span class="muted"><?= e(\App\Catalog\CatalogRepository::modalities()[$item['modality']] ?? ucfirst((string)$item['modality'])) ?></span>
                </li>
                <li><strong>Duración:</strong> <?= e($item['duration_label'] ?? '—') ?></li>
                <li><strong>Audiencia:</strong> <?= e($item['audience'] ?? '—') ?></li>
                <?php
                $scoreRanges = \App\Catalog\CatalogRepository::decodeScoreRanges($item['score_ranges_json'] ?? null);
                ?>
                <li><strong>Rangos:</strong>
                    <?php if ($scoreRanges): ?>
                        <ul class="score-ranges-display">
                            <?php foreach ($scoreRanges as $r): ?>
                                <?php
                                $span = trim($r['min'] . ($r['min'] !== '' && $r['max'] !== '' ? ' – ' : '') . $r['max']);
                                $line = $span !== '' && $r['label'] !== ''
                                    ? $span . ' = ' . $r['label']
                                    : ($r['label'] !== '' ? $r['label'] : $span);
                                ?>
                                <li><?= e($line) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <?= e($item['score_range'] ?? '—') ?>
                    <?php endif; ?>
                </li>
                <?php if (!empty($item['provider_brand_website'])): ?>
                    <li><strong>Sitio oficial:</strong>
                        <a href="<?= e($item['provider_brand_website']) ?>" target="_blank" rel="noopener">
                            <?= e(preg_replace('#^https?://#i', '', rtrim((string)$item['provider_brand_website'], '/'))) ?>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (!empty($item['is_level_exam'])): ?>
                    <?php
                    $skillsIcons = \App\Support\CertIcons::skillsHtml(
                        $item['skills_json'] ?? null,
                        true,
                        'cert-meta-icons--lg'
                    );
                    ?>
                    <?php if ($skillsIcons !== ''): ?>
                        <li><strong>Habilidades:</strong> <?= $skillsIcons ?></li>
                    <?php endif; ?>
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

    <h2>Cómo se aplica (protocolo)</h2>
    <?php
    $protocolSteps = $protocolSteps ?? [];
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
    ?>
    <?php if ($protocolSteps): ?>
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
                    <?php if (!empty($step['description'])): ?>
                        <p class="muted"><?= e($step['description']) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php elseif (!empty($item['protocol_procedure_html'])): ?>
        <div class="prose"><?= $item['protocol_procedure_html'] ?></div>
    <?php else: ?>
        <p class="muted">Sin protocolo de pasos aún.</p>
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

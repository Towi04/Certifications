<?php
/** @var array $item */
/** @var array|null $partnerPrice */
/** @var array $courses */
?>
<section class="page-head">
    <div>
        <p class="eyebrow"><?= e($item['provider_name']) ?></p>
        <h1><?= e($item['name']) ?></h1>
        <p class="muted"><?= e($item['short_description'] ?? '') ?></p>
    </div>
    <div class="actions">
        <a class="btn btn-ghost" href="/partner">Volver al catálogo</a>
    </div>
</section>

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
                <li><strong>Código:</strong> <?= e($item['code']) ?></li>
                <li><strong>Modalidad:</strong> <?= e($item['modality']) ?></li>
                <li><strong>Duración:</strong> <?= e($item['duration_label'] ?? '—') ?></li>
                <li><strong>Audiencia:</strong> <?= e($item['audience'] ?? '—') ?></li>
                <li><strong>Protocolo:</strong> <?= e($item['protocol_name'] ?? '—') ?></li>
            </ul>
        </div>
        <div>
            <h2>Trámites SEP</h2>
            <ul class="facts">
                <li><strong>CENNI:</strong>
                    <?php if ((int)$item['cenni_eligible']): ?>
                        Sí · <?= e($item['cenni_doc_type']) ?>
                        <?= (int)$item['cenni_included'] ? ' · incluido' : ' · ' . e(\App\Support\Str::money(isset($item['cenni_fee']) ? (float)$item['cenni_fee'] : null)) ?>
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

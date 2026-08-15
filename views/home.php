<?php
$featured = $featured ?? [];
$groups = $groups ?? [];
$courses = $courses ?? [];
?>
<section class="store-hero">
    <h1>Elige tu certificación</h1>
</section>

<?php if ($featured): ?>
<section class="store-section" id="estrellas">
    <div class="section-head">
        <h2>Productos estrella</h2>
    </div>
    <div class="store-featured-grid">
        <?php foreach ($featured as $item): ?>
            <a class="store-card store-card--featured" href="/certificacion?slug=<?= e(rawurlencode($item['slug'])) ?>">
                <p class="eyebrow"><?= e($item['provider_name']) ?></p>
                <h3><?= e($item['name']) ?></h3>
                <?php
                $maxPoints = 4;
                require __DIR__ . '/partials/catalog_card_value.php';
                ?>
                <p class="price">
                    <?php if ($item['public_price'] !== null): ?>
                        <?= e(\App\Support\Str::money((float)$item['public_price'], $item['currency'] ?? 'MXN')) ?>
                    <?php else: ?>
                        Consultar
                    <?php endif; ?>
                </p>
                <span class="store-card-cta">Ver ficha →</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="store-section" id="catalogo">
    <div class="section-head">
        <h2>Catálogo por certificadora</h2>
    </div>

    <?php if (!$groups && !$featured): ?>
        <p class="muted">Pronto publicaremos el catálogo. Mientras tanto, escribe a Instituto Doceo.</p>
    <?php endif; ?>

    <?php foreach ($groups as $group): ?>
        <div class="store-provider-block">
            <header class="store-provider-head">
                <?php if (!empty($group['logo'])): ?>
                    <img class="store-provider-logo" src="/media?f=<?= e(rawurlencode($group['logo'])) ?>" alt="">
                <?php endif; ?>
                <h3><?= e($group['provider_name']) ?></h3>
            </header>
            <div class="store-grid">
                <?php foreach ($group['items'] as $item): ?>
                    <a class="store-card" href="/certificacion?slug=<?= e(rawurlencode($item['slug'])) ?>">
                        <h4><?= e($item['name']) ?></h4>
                        <?php
                        $maxPoints = 3;
                        require __DIR__ . '/partials/catalog_card_value.php';
                        ?>
                        <p class="price">
                            <?php if ($item['public_price'] !== null): ?>
                                <?= e(\App\Support\Str::money((float)$item['public_price'], $item['currency'] ?? 'MXN')) ?>
                            <?php else: ?>
                                Consultar
                            <?php endif; ?>
                        </p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<?php if ($courses): ?>
<section class="store-section" id="cursos">
    <div class="section-head">
        <h2>Cursos</h2>
        <p class="muted">Preparación y plataformas asociadas a nuestras certificaciones.</p>
    </div>
    <div class="store-grid">
        <?php foreach ($courses as $course): ?>
            <?php
            $platformRaw = strtolower(trim((string) ($course['platform_type'] ?? '')));
            $platformLabel = match ($platformRaw) {
                'moodle' => 'Campus',
                '' => '',
                default => (string) ($course['platform_type'] ?? ''),
            };
            ?>
            <article class="store-card store-card--course">
                <a class="store-card-link" href="/curso?id=<?= (int) $course['id'] ?>">
                <?php if (!empty($course['course_logo_path'])): ?>
                    <div class="catalog-card-logo store-card-logo">
                        <img src="/media?f=<?= e(rawurlencode((string) $course['course_logo_path'])) ?>" alt="" loading="lazy">
                    </div>
                <?php endif; ?>
                <?php if ($platformLabel !== ''): ?>
                    <p class="eyebrow"><?= e($platformLabel) ?></p>
                <?php endif; ?>
                <h4><?= e($course['name']) ?></h4>
                <p class="muted"><?= e(mb_substr(trim(strip_tags((string)($course['description'] ?? $course['access_notes'] ?? ''))), 0, 120)) ?></p>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

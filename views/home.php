<?php
$featured = $featured ?? [];
$groups = $groups ?? [];
$courses = $courses ?? [];
?>
<section class="store-hero">
    <img class="store-hero__logo" src="/assets/brand/logo-doceo.svg" width="320" height="107" alt="Instituto DOCEO">
    <p class="eyebrow">Certificaciones</p>
    <h1>Elige tu certificación</h1>
    <div class="actions">
        <a class="btn" href="#catalogo">Ver catálogo</a>
        <?php if (\App\Auth\Auth::check()): ?>
            <?php $u = \App\Auth\Auth::user(); $role = $u['role'] ?? ''; ?>
            <?php if ($role === 'student'): ?>
                <a class="btn btn-ghost" href="/alumno">Mi seguimiento</a>
            <?php elseif (\App\Auth\Auth::isStaffRole($role)): ?>
                <a class="btn btn-ghost" href="/admin">Administración</a>
            <?php elseif ($role === 'partner'): ?>
                <a class="btn btn-ghost" href="/partner">Catálogo TR</a>
            <?php endif; ?>
        <?php else: ?>
            <a class="btn btn-ghost" href="/login">Ya tengo cuenta</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($featured): ?>
<section class="store-section" id="estrellas">
    <div class="section-head">
        <h2>Productos estrella</h2>
        <p class="muted">ELET, ITEP, TOEFL, Linguaskill, Excel y más destacados.</p>
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
        <p class="muted">Todas las certificaciones publicadas, agrupadas por empresa (incluye las estrellas).</p>
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
            <article class="store-card store-card--course">
                <p class="eyebrow"><?= e($course['platform_type']) ?></p>
                <h4><?= e($course['name']) ?></h4>
                <p class="muted"><?= e(mb_substr(trim(strip_tags((string)($course['description'] ?? $course['access_notes'] ?? ''))), 0, 120)) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

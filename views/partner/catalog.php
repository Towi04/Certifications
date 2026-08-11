<section class="page-head">
    <div>
        <h1>Catálogo Teacher Referral</h1>
        <p class="muted">
            <?php if ($partner): ?>
                <?= e($partner['organization'] ?: ($user['name'] ?? '')) ?>
                · <?= e($partner['tier_name'] ?? 'Sin nivel') ?>
                <?php if (!empty($partner['agreement_name'])): ?>
                    · <?= e($partner['agreement_name']) ?>
                <?php endif; ?>
            <?php else: ?>
                Tu usuario partner aún no tiene ficha de convenio asignada. Puedes ver fichas; los precios partner aparecerán cuando admin te asigne nivel/convenio.
            <?php endif; ?>
        </p>
    </div>
    <a class="btn btn-ghost" href="/partner/convenio">Mi convenio</a>
</section>

<?php if (!empty($error)): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<?php if (!empty($info)): ?><p class="info"><?= e($info) ?></p><?php endif; ?>

<?php if (isset($canRegister) && !$canRegister): ?>
<section class="note">
    <p>
        Acceso limitado: puedes consultar el catálogo, pero <strong>no registrar alumnos</strong>
        hasta firmar el convenio vigente y que Doceo lo confirme.
        <a href="/partner/convenio">Ir a mi convenio</a>
    </p>
</section>
<?php endif; ?>

<form method="get" class="filters note stack form-grid">
    <label>Buscar<input name="q" value="<?= e($filters['q'] ?? '') ?>"></label>
    <label>Proveedor
        <select name="provider_id">
            <option value="">Todos</option>
            <?php foreach ($providers as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (string)($filters['provider_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>CENNI
        <select name="cenni">
            <option value="">Todos</option>
            <option value="1" <?= ($filters['cenni'] ?? '') === '1' ? 'selected' : '' ?>>Elegible</option>
            <option value="0" <?= ($filters['cenni'] ?? '') === '0' ? 'selected' : '' ?>>No</option>
        </select>
    </label>
    <button class="btn" type="submit">Filtrar</button>
</form>

<div class="catalog-grid">
    <?php foreach ($items as $item): ?>
        <article class="catalog-card">
            <p class="eyebrow"><?= e($item['provider_name']) ?></p>
            <h2><a href="/partner/certificacion?slug=<?= e(rawurlencode($item['slug'])) ?>"><?= e($item['name']) ?></a></h2>
            <?php
            $maxPoints = 4;
            require __DIR__ . '/../partials/catalog_card_value.php';
            ?>
            <p class="price">
                <?php if ($item['partner_price'] !== null): ?>
                    Tu precio: <strong><?= e(\App\Support\Str::money((float)$item['partner_price'], $item['partner_currency'] ?? 'MXN')) ?></strong>
                <?php else: ?>
                    Precio partner: pendiente
                <?php endif; ?>
            </p>
            <p class="meta-line catalog-meta-icons">
                <?= \App\Support\CertIcons::modalityHtml((string)($item['modality'] ?? '')) ?>
                <?php
                $skillsIcons = \App\Support\CertIcons::skillsHtml(
                    $item['skills_json'] ?? null,
                    !empty($item['is_level_exam'])
                );
                if ($skillsIcons !== '') {
                    echo ' ' . $skillsIcons;
                }
                ?>
                <?php if ((int)$item['cenni_eligible'] || (int)$item['conocer_eligible']): ?>
                    <span class="muted">
                        <?= (int)$item['cenni_eligible'] ? 'CENNI' : '' ?>
                        <?= (int)$item['conocer_eligible'] ? (((int)$item['cenni_eligible'] ? ' · ' : '') . 'CONOCER') : '' ?>
                    </span>
                <?php endif; ?>
            </p>
        </article>
    <?php endforeach; ?>
    <?php if (!$items): ?>
        <p class="muted">No hay certificaciones publicadas con esos filtros.</p>
    <?php endif; ?>
</div>

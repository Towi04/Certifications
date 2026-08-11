<?php
/**
 * Cabecera + pestañas de ficha admin (mismo look que proveedores).
 *
 * Variables:
 * - $fichaTitle (string)
 * - $fichaSubtitle (string|null)
 * - $fichaBackUrl (string)
 * - $fichaBackLabel (string|null)
 * - $tabs (array<string,string>) tabId => label
 * - $tab (string) active tab
 * - $fichaTabBase (string) URL base sin tab, e.g. /admin/x/edit?id=1
 * - $fichaMode ('url'|'js') default url — js usa botones y data-admin-ficha
 * - $fichaLogo (string|null) media path
 * - $fichaInitial (string|null) fallback letter
 */
$fichaTitle = $fichaTitle ?? ($title ?? 'Ficha');
$fichaSubtitle = $fichaSubtitle ?? null;
$fichaBackUrl = $fichaBackUrl ?? '/admin';
$fichaBackLabel = $fichaBackLabel ?? 'Volver al listado';
$tabs = $tabs ?? [];
$tab = $tab ?? (array_key_first($tabs) ?: 'general');
$fichaTabBase = $fichaTabBase ?? '';
$fichaMode = $fichaMode ?? 'url';
$fichaLogo = $fichaLogo ?? null;
$fichaInitial = $fichaInitial ?? mb_substr((string) $fichaTitle, 0, 1);
?>
<header class="admin-ficha-head">
    <div class="admin-ficha-identity">
        <?php if ($fichaLogo): ?>
            <img class="admin-ficha-logo" src="/media?f=<?= e(rawurlencode($fichaLogo)) ?>" alt="" width="56" height="56">
        <?php else: ?>
            <span class="admin-ficha-fallback"><?= e($fichaInitial) ?></span>
        <?php endif; ?>
        <div>
            <h2><?= e($fichaTitle) ?></h2>
            <?php if ($fichaSubtitle): ?>
                <p class="muted"><?= $fichaSubtitle ?></p>
            <?php endif; ?>
        </div>
    </div>
    <a class="btn btn-ghost" href="<?= e($fichaBackUrl) ?>"><?= e($fichaBackLabel) ?></a>
</header>

<?php if ($tabs): ?>
<nav class="admin-ficha-tabs" aria-label="Secciones" <?= $fichaMode === 'js' ? 'data-admin-ficha-nav' : '' ?>>
    <?php foreach ($tabs as $key => $label): ?>
        <?php if ($fichaMode === 'js'): ?>
            <button type="button"
                    class="admin-ficha-tab <?= $tab === $key ? 'is-active' : '' ?>"
                    data-tab-target="<?= e($key) ?>"
                    aria-selected="<?= $tab === $key ? 'true' : 'false' ?>">
                <?= e($label) ?>
            </button>
        <?php elseif ($fichaTabBase !== ''): ?>
            <?php
            $sep = str_contains($fichaTabBase, '?') ? '&' : '?';
            $href = $fichaTabBase . $sep . 'tab=' . rawurlencode((string) $key);
            ?>
            <a class="admin-ficha-tab <?= $tab === $key ? 'is-active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php else: ?>
            <span class="admin-ficha-tab <?= $tab === $key ? 'is-active' : '' ?>"><?= e($label) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

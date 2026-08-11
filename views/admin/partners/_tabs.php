<?php
/**
 * Pestañas de la sección Partners TR.
 * @var string $partnersTab partners|niveles
 */
$partnersTab = $partnersTab ?? 'partners';
?>
<nav class="provider-tabs" aria-label="Secciones Partners TR" style="margin-bottom:1rem">
    <a class="provider-tab <?= $partnersTab === 'partners' ? 'is-active' : '' ?>" href="/admin/partners">Partners</a>
    <a class="provider-tab <?= $partnersTab === 'niveles' ? 'is-active' : '' ?>" href="/admin/partners?tab=niveles">Niveles TR</a>
</nav>

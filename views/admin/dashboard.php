<section class="page-head">
    <div>
        <h1>Administración</h1>
        <p class="muted">Hola, <?= e($user['name'] ?? $user['email'] ?? '') ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="/admin/salud">Salud del sistema</a>
        <a class="btn btn-ghost" href="/admin/settings">Integraciones</a>
        <a class="btn btn-ghost" href="/admin/certifications">Certificaciones</a>
    </div>
</section>

<?php
$counts = $counts ?? [
    'providers' => 0, 'certifications' => 0, 'published' => 0,
    'courses' => 0, 'partners' => 0, 'agreements' => 0,
];
?>

<div class="health-grid">
    <article class="health-card"><header><h2>Proveedores</h2><span class="pill"><?= (int)$counts['providers'] ?></span></header><p><a href="/admin/providers">Administrar</a></p></article>
    <article class="health-card"><header><h2>Certificaciones</h2><span class="pill"><?= (int)$counts['certifications'] ?></span></header><p><?= (int)$counts['published'] ?> publicadas · <a href="/admin/certifications">Administrar</a></p></article>
    <article class="health-card"><header><h2>Cursos</h2><span class="pill"><?= (int)$counts['courses'] ?></span></header><p><a href="/admin/courses">Administrar</a></p></article>
    <article class="health-card"><header><h2>Protocolos</h2></header><p><a href="/admin/protocols">Administrar</a></p></article>
    <article class="health-card"><header><h2>Niveles TR</h2></header><p><a href="/admin/tiers">Administrar</a></p></article>
    <article class="health-card"><header><h2>Convenios</h2><span class="pill"><?= (int)$counts['agreements'] ?></span></header><p><a href="/admin/agreements">Versiones y precios</a></p></article>
    <article class="health-card"><header><h2>Partners TR</h2><span class="pill"><?= (int)$counts['partners'] ?></span></header><p><a href="/admin/partners">Asignar nivel/convenio</a></p></article>
</div>

<section class="note">
    <h2>Flujo sugerido</h2>
    <ol>
        <li>Importa <code>sql/seed.sql</code> si aún no tienes proveedores/niveles.</li>
        <li>Carga certificaciones y márcalas como <strong>publicadas</strong>.</li>
        <li>En el convenio vigente de cada nivel, asigna precios partner.</li>
        <li>Los Teacher Referral ven el catálogo en <a href="/partner">/partner</a>.</li>
    </ol>
</section>

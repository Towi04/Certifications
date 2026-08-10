<?php
/** @var string $title */
/** @var string|null $info */
/** @var string|null $error */
?>
<section class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <p class="muted">Catálogo y operación · Instituto Doceo</p>
    </div>
    <div class="actions">
        <a class="btn btn-ghost" href="/admin">Panel</a>
    </div>
</section>

<nav class="admin-nav">
    <a href="/admin/providers">Proveedores</a>
    <a href="/admin/documents">Documentos</a>
    <a href="/admin/inventory">Inventario</a>
    <a href="/admin/protocols">Protocolos</a>
    <a href="/admin/pendientes">Pendientes</a>
    <a href="/admin/cases">Casos</a>
    <a href="/admin/courses">Cursos</a>
    <a href="/admin/certifications">Certificaciones</a>
    <a href="/admin/certifications/pricing">Precios</a>
    <a href="/admin/tiers">Niveles TR</a>
    <a href="/admin/agreements">Convenios anuales</a>
    <a href="/admin/partners">Partners</a>
    <a href="/admin/users">Usuarios</a>
    <a href="/admin/mail-templates">Correos</a>
    <a href="/admin/reglamentos-firmados">Firmas</a>
    <a href="/admin/openpay">OpenPay</a>
</nav>

<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (!empty($info)): ?><div class="alert alert-ok"><?= e($info) ?></div><?php endif; ?>

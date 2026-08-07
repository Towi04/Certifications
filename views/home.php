<section class="hero">
    <p class="eyebrow">Punto de venta · Teacher Referral</p>
    <h1><?= e(app_name()) ?></h1>
    <p class="slogan-hero">be different, be better</p>
    <p class="lede">
        Catálogo y automatización de certificaciones. Un solo panal para courses,
        convenios y seguimiento de Teacher Referral.
    </p>
    <div class="actions">
        <?php if (\App\Auth\Auth::check()): ?>
            <?php $u = \App\Auth\Auth::user(); ?>
            <?php if ($u && in_array($u['role'], ['partner', 'admin'], true)): ?>
                <a class="btn" href="/partner">Ver catálogo TR</a>
            <?php endif; ?>
            <?php if ($u && ($u['role'] ?? '') === 'admin'): ?>
                <a class="btn btn-ghost" href="/admin">Administración</a>
            <?php else: ?>
                <a class="btn btn-ghost" href="/profile">Mi perfil</a>
            <?php endif; ?>
        <?php else: ?>
            <a class="btn" href="/login">Entrar</a>
            <a class="btn btn-ghost" href="/register">Registro</a>
        <?php endif; ?>
    </div>
</section>

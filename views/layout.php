<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? 'PDV') . ' · ' . app_name()) ?></title>
    <link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/assets/brand/favicon.png" type="image/png" sizes="64x64">
    <link rel="apple-touch-icon" href="/assets/brand/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= e((string) (@filemtime(BASE_PATH . '/public/assets/css/app.css') ?: time())) ?>">
</head>
<body>
    <div class="honeycomb-bg" aria-hidden="true"></div>
    <header class="site-header">
        <div class="wrap header-inner">
            <a class="brand" href="/">
                <img class="brand-logo" src="/assets/brand/logo-doceo.svg" width="200" height="67" alt="<?= e(app_name()) ?>">
            </a>
            <nav class="nav">
                <a href="/#catalogo">Catálogo</a>
                <?php if (\App\Auth\Auth::check()): ?>
                    <?php $u = \App\Auth\Auth::user(); $role = $u['role'] ?? ''; ?>
                    <?php if ($role === 'student'): ?>
                        <a href="/alumno">Mi seguimiento</a>
                    <?php endif; ?>
                    <a href="/profile">Perfil</a>
                    <?php if ($u && in_array($role, ['partner', 'admin', 'assistant', 'manager'], true)): ?>
                        <?php if ($role === 'partner' || \App\Auth\Auth::isStaffRole($role)): ?>
                            <a href="/partner">Catálogo TR</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($u && \App\Auth\Auth::isStaffRole($role)): ?>
                        <a href="/admin">Admin</a>
                    <?php endif; ?>
                    <form class="inline-form" method="post" action="/logout">
                        <button type="submit" class="linkish">Salir</button>
                    </form>
                <?php else: ?>
                    <a href="/login">Entrar</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="wrap main">
        <?php require $viewFile; ?>
    </main>
    <footer class="site-footer">
        <div class="wrap footer-inner">
            <div class="footer-brand">
                <img src="/assets/brand/escudo.svg" width="36" height="43" alt="">
                <div>
                    <p>PDV Certificaciones · <?= e(app_name()) ?></p>
                    <p class="slogan">be different, be better</p>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>

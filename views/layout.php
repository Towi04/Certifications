<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? 'PDV') . ' · ' . app_name()) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="honeycomb-bg" aria-hidden="true"></div>
    <header class="site-header">
        <div class="wrap header-inner">
            <a class="brand" href="/">
                <span class="brand-hex" aria-hidden="true">⬡</span>
                <span class="brand-text">
                    <strong><?= e(app_name()) ?></strong>
                    <small>be different, be better</small>
                </span>
            </a>
            <nav class="nav">
                <?php if (\App\Auth\Auth::check()): ?>
                    <?php $u = \App\Auth\Auth::user(); ?>
                    <?php if ($u && $u['role'] === 'admin'): ?>
                        <a href="/admin">Admin</a>
                        <a href="/admin/salud">Salud</a>
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
            <p>PDV Certificaciones · <?= e(app_name()) ?></p>
            <p class="slogan">be different, be better</p>
        </div>
    </footer>
</body>
</html>

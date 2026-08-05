<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? 'PDV') . ' · ' . app_name()) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <header class="site-header">
        <div class="wrap header-inner">
            <a class="brand" href="/"><?= e(app_name()) ?></a>
            <nav class="nav">
                <?php if (\App\Auth\Auth::check()): ?>
                    <?php $u = \App\Auth\Auth::user(); ?>
                    <?php if ($u && $u['role'] === 'admin'): ?>
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
        <div class="wrap">
            <p>PDV Certificaciones · <?= e(app_name()) ?></p>
        </div>
    </footer>
</body>
</html>

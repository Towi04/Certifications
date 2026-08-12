<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? 'PDV') . ' · ' . app_name()) ?></title>
    
    <link rel="icon" href="/assets/brand/favicon.png" type="image/png" sizes="64x64">
    <link rel="apple-touch-icon" href="/assets/brand/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= e((string) (@filemtime(BASE_PATH . '/public/assets/css/app.css') ?: time())) ?>">
</head>
<body class="layout-bare<?= !empty($print) ? ' layout-print' : '' ?>">
    <?php require $viewFile; ?>
</body>
</html>

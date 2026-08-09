<?php
$counts = $counts ?? [
    'providers' => 0, 'certifications' => 0, 'published' => 0,
    'courses' => 0, 'partners' => 0, 'agreements' => 0, 'protocols' => 0, 'tiers' => 0, 'users' => 0,
];

$menus = [
    [
        'href' => '/admin/users',
        'title' => 'Usuarios',
        'desc' => 'Personal Doceo y Partners TR',
        'count' => (int) ($counts['users'] ?? 0),
        'tone' => 'slate',
        'icon' => 'id',
    ],
    [
        'href' => '/admin/providers',
        'title' => 'Proveedores',
        'desc' => 'Casas certificadoras y logos',
        'count' => (int) $counts['providers'],
        'tone' => 'blue',
        'icon' => 'building',
    ],
    [
        'href' => '/admin/documents',
        'title' => 'Documentos',
        'desc' => 'Reglamentos y archivos para alumnos',
        'count' => (int) ($counts['documents'] ?? 0),
        'tone' => 'green',
        'icon' => 'file',
    ],
    [
        'href' => '/admin/certifications',
        'title' => 'Certificaciones',
        'desc' => (int) $counts['published'] . ' publicadas · fichas de producto',
        'count' => (int) $counts['certifications'],
        'tone' => 'yellow',
        'icon' => 'badge',
    ],
    [
        'href' => '/admin/certifications/pricing',
        'title' => 'Precios / reglamentos',
        'desc' => 'Matriz masiva por empresa (costo, público, TR)',
        'count' => (int) $counts['certifications'],
        'tone' => 'yellow',
        'icon' => 'badge',
    ],
    [
        'href' => '/admin/protocols',
        'title' => 'Protocolos',
        'desc' => 'Pasos pre / examen / post',
        'count' => (int) ($counts['protocols'] ?? 0),
        'tone' => 'indigo',
        'icon' => 'list',
    ],
    [
        'href' => '/admin/cases',
        'title' => 'Casos',
        'desc' => 'Progreso del alumno en el flujo',
        'count' => (int) ($counts['cases'] ?? 0),
        'tone' => 'slate',
        'icon' => 'id',
    ],
    [
        'href' => '/admin/courses',
        'title' => 'Cursos',
        'desc' => 'Moodle, externos y paquetes',
        'count' => (int) $counts['courses'],
        'tone' => 'teal',
        'icon' => 'book',
    ],
    [
        'href' => '/admin/tiers',
        'title' => 'Niveles TR',
        'desc' => 'Niveles Teacher Referral',
        'count' => (int) ($counts['tiers'] ?? 0),
        'tone' => 'amber',
        'icon' => 'layers',
    ],
    [
        'href' => '/admin/agreements',
        'title' => 'Convenios',
        'desc' => 'Versiones anuales y precios',
        'count' => (int) $counts['agreements'],
        'tone' => 'green',
        'icon' => 'file',
    ],
    [
        'href' => '/admin/partners',
        'title' => 'Partners TR',
        'desc' => 'Asignar nivel y convenio',
        'count' => (int) $counts['partners'],
        'tone' => 'coral',
        'icon' => 'users',
    ],
    [
        'href' => '/admin/salud',
        'title' => 'Salud',
        'desc' => 'DB, Moodle, OpenPay, SMTP',
        'count' => null,
        'tone' => 'slate',
        'icon' => 'list',
    ],
];

$icons = [
    'id' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><circle cx="9" cy="12" r="2.4" stroke="currentColor" stroke-width="1.8"/><path d="M13.5 10.5h4M13.5 13.5h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    'building' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20V8l8-4 8 4v12" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 20v-6h6v6M9 10h.01M15 10h.01M12 10h.01M9 14h.01M15 14h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    'badge' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="9" r="5.5" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 13.5 7 20l5-2.5L17 20l-1.5-6.5" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
    'book' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 5.5A2.5 2.5 0 0 1 7.5 3H19v15H7.5A2.5 2.5 0 0 0 5 20.5V5.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M5 18.5A2.5 2.5 0 0 1 7.5 16H19" stroke="currentColor" stroke-width="1.8"/></svg>',
    'list' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 7h10M9 12h10M9 17h10M5 7h.01M5 12h.01M5 17h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    'layers' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
    'file' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 3v5h5M9 13h6M9 17h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'users' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="8" r="3.2" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 19c.6-3 2.8-4.5 5.5-4.5S14.4 16 15 19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="17" cy="9" r="2.4" stroke="currentColor" stroke-width="1.8"/><path d="M16 14.5c2 .3 3.6 1.5 4.2 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
];
?>

<section class="admin-hero">
    <div class="admin-hero-copy">
        <p class="eyebrow">Panel de administración</p>
        <h1>Hola, <?= e($user['name'] ?? $user['email'] ?? 'Admin') ?></h1>
        <p class="lede">Gestiona usuarios, catálogo, convenios y partners Teacher Referral desde aquí.</p>
    </div>
    <div class="admin-hero-visual" aria-hidden="true">
        <span class="hex hex-a"></span>
        <span class="hex hex-b"></span>
        <span class="hex hex-c"></span>
    </div>
</section>

<div class="admin-menu-grid">
    <?php foreach ($menus as $menu): ?>
        <a class="admin-menu-card tone-<?= e($menu['tone']) ?>" href="<?= e($menu['href']) ?>">
            <span class="admin-menu-icon"><?= $icons[$menu['icon']] ?></span>
            <span class="admin-menu-body">
                <span class="admin-menu-title"><?= e($menu['title']) ?></span>
                <span class="admin-menu-desc"><?= e($menu['desc']) ?></span>
            </span>
            <span class="admin-menu-count"><?= (int) $menu['count'] ?></span>
        </a>
    <?php endforeach; ?>
</div>

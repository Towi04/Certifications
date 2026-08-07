<?php
/** @var list<array{name: string, ok: bool|null, message: string, meta?: array<string, mixed>}> $results */
/** @var array{email?: string}|null $user */
?>
<section class="page-head">
    <div>
        <h1>Salud del sistema</h1>
        <p class="muted">Pruebas de conexión · <?= e($user['email'] ?? '') ?></p>
    </div>
    <div class="actions">
        <a class="btn btn-ghost" href="/admin/salud">Reprobar DB / Moodle / OpenPay</a>
        <a class="btn" href="/admin/salud?smtp=1">Probar SMTP</a>
    </div>
</section>

<div class="health-grid">
    <?php foreach ($results as $item): ?>
        <?php
        $ok = $item['ok'];
        $statusClass = $ok === true ? 'ok' : ($ok === false ? 'bad' : 'skip');
        $label = $ok === true ? 'OK' : ($ok === false ? 'Error' : 'Pendiente');
        ?>
        <article class="health-card <?= e($statusClass) ?>">
            <header>
                <h2><?= e($item['name']) ?></h2>
                <span class="pill"><?= e($label) ?></span>
            </header>
            <p><?= e($item['message']) ?></p>
            <?php if (!empty($item['meta'])): ?>
                <pre class="meta"><?= e_json($item['meta']) ?></pre>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>

<section class="note">
    <h2>SMTP y error 535</h2>
    <p>
        El alta de usuarios y este botón usan el <strong>mismo</strong> cliente SMTP.
        El Mailer prueba varios endpoints: el del <code>.env</code>, <code>localhost</code>,
        <code>127.0.0.1</code> y puerto <code>587/tls</code> (típico en Neubox).
    </p>
    <ol>
        <li>Compara <code>pass_len</code> con la longitud real de la contraseña en cPanel. Si no coincide, ponla entre comillas dobles en el <code>.env</code>.</li>
        <li>Entra a webmail con esa cuenta para confirmar que la clave es válida.</li>
        <li>Si un endpoint funciona, el meta mostrará <code>used_endpoint</code>; puedes dejarlo fijo en el <code>.env</code>.</li>
    </ol>
</section>

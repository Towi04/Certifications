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
        El alta de usuarios y este botón usan el <strong>mismo</strong> cliente SMTP y el mismo <code>.env</code>.
        Si “Probar SMTP” falla con 535, Exim está rechazando la autenticación en este momento
        (no es un bug del formulario de usuarios).
    </p>
    <ol>
        <li>Revisa en el meta de SMTP: <code>user</code> y <code>pass_len</code> (longitud leída del .env, sin mostrar la clave).</li>
        <li>Entra a webmail de <code>certificaciones@institutodoceo.com</code> con la misma contraseña.</li>
        <li>Si webmail funciona pero SMTP no: prueba <code>SMTP_HOST=localhost</code> o puerto <code>587</code> + <code>SMTP_ENCRYPTION=tls</code>.</li>
    </ol>
</section>

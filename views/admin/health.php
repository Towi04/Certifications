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
    <h2>Correo y error 535</h2>
    <p>
        Entrar a <strong>webmail</strong> valida IMAP (Dovecot), no SMTP AUTH de Exim.
        Por eso la misma clave puede abrir webmail y fallar en 465/587 desde PHP.
        En modo <code>SMTP_TRANSPORT=auto</code> el sistema envía primero con
        <code>mail()</code> local (sin AUTH), que es lo normal en Neubox.
    </p>
    <ol>
        <li>Tras “Probar SMTP”, si sale OK con <code>used_endpoint.transport = mail</code>, el correo ya funciona.</li>
        <li>Si SMTP AUTH sigue en 535: cPanel → cPHulk (desbloquea) o restablece la clave del buzón.</li>
        <li>Opcional: fija <code>SMTP_TRANSPORT=mail</code> en el <code>.env</code>.</li>
    </ol>
</section>

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
                <pre class="meta"><?= e(json_encode($item['meta'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>

<section class="note">
    <h2>Cómo completar el .env en el servidor</h2>
    <ol>
        <li>Copia <code>.env.example</code> a <code>.env</code> en la raíz del proyecto (si aún no existe).</li>
        <li>Completa <code>DB_PASS</code>, <code>MOODLE_TOKEN</code>, <code>OPENPAY_PRIVATE_KEY</code> y <code>SMTP_PASS</code>.</li>
        <li>Importa <code>sql/schema.sql</code> y <code>sql/seed.sql</code> en phpMyAdmin.</li>
        <li>Entra con el admin de <code>ADMIN_EMAIL</code> / <code>ADMIN_PASSWORD</code> y cambia la contraseña después.</li>
        <li>Borra o protege <code>test_moodle.php</code> del servidor cuando ya no lo necesites.</li>
    </ol>
</section>

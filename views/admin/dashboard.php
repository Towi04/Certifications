<section class="page-head">
    <div>
        <h1>Administración</h1>
        <p class="muted">Hola, <?= e($user['name'] ?? $user['email'] ?? '') ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="/admin/salud">Salud del sistema</a>
    </div>
</section>

<section class="note">
    <h2>Siguiente paso</h2>
    <p class="muted">
        Abre <a href="/admin/salud">Salud del sistema</a> para verificar MariaDB, Moodle, OpenPay y storage.
        El botón “Probar SMTP” envía un correo real.
    </p>
</section>

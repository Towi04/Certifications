<?php require __DIR__ . '/_nav.php'; ?>
<section class="note">
    <h2>Settings de integraciones</h2>
    <p class="muted">
        Los secretos se editan solo en el archivo <code>.env</code> del servidor (cPanel).
        Esta pantalla muestra qué falta configurar, sin revelar contraseñas ni tokens.
    </p>
    <div class="actions" style="margin:1rem 0">
        <a class="btn" href="/admin/salud">Probar conexiones (Salud)</a>
        <a class="btn btn-ghost" href="/admin/salud?smtp=1">Probar SMTP</a>
    </div>
</section>

<?php
$groups = [];
foreach ($checklist as $row) {
    $groups[$row['group']][] = $row;
}
?>

<?php foreach ($groups as $group => $rows): ?>
<section class="note">
    <h3><?= e($group) ?></h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Variable</th><th>Estado</th><th>Detalle</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <strong><?= e($row['label']) ?></strong><br>
                        <code><?= e($row['key']) ?></code>
                    </td>
                    <td>
                        <?php if ($row['configured']): ?>
                            <span class="pill" style="background:#e5f5ec;color:var(--ok)">Configurado</span>
                        <?php else: ?>
                            <span class="pill" style="background:#f8e4e4;color:var(--bad)">Falta</span>
                        <?php endif; ?>
                    </td>
                    <td class="muted"><?= e($row['hint']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endforeach; ?>

<section class="note">
    <h3>Checklist operativo</h3>
    <ol>
        <li>Completa <code>.env</code> en el servidor (ver <code>docs/setup.md</code>).</li>
        <li>Importa <code>sql/schema.sql</code> y <code>sql/seed.sql</code>.</li>
        <li>Verifica semáforos en <a href="/admin/salud">Salud</a>.</li>
        <li>Borra <code>test_moodle.php</code> del servidor cuando ya no lo uses.</li>
        <li>Carga proveedores → protocolos → certificaciones (+ assets) → precios de convenio → partners.</li>
    </ol>
</section>

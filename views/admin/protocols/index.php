<?php require __DIR__ . '/../_nav.php'; ?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Protocolos</h2>
            <p class="muted" style="margin:0.35rem 0 0">Flujos de pasos pre / durante / post examen para certificaciones y cursos.</p>
        </div>
        <div class="actions">
            <a class="btn btn-ghost" href="/admin/cases">Casos</a>
            <a class="btn" href="/admin/protocols/create">Nuevo</a>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Código</th><th>Nombre</th><th>Proveedor</th><th>Pasos</th><th>Modalidad</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><code><?= e($item['code']) ?></code></td>
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($item['provider_name'] ?? '—') ?></td>
                    <td><?= (int)($item['steps_count'] ?? 0) ?></td>
                    <td><?= e($item['modality']) ?></td>
                    <td><a href="/admin/protocols/edit?id=<?= (int)$item['id'] ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

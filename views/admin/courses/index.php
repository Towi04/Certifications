<?php require __DIR__ . '/../_nav.php'; ?>
<section class="note">
    <div class="page-head" style="margin:0">
        <h2 style="margin:0">Cursos</h2>
        <a class="btn" href="/admin/courses/create">Nuevo</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Código</th><th>Nombre</th><th>Plataforma</th><th>Moodle ID</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><code><?= e($item['code']) ?></code></td>
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($item['platform_type']) ?></td>
                    <td><?= e((string)($item['moodle_course_id'] ?? '—')) ?></td>
                    <td><a href="/admin/courses/edit?id=<?= (int)$item['id'] ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

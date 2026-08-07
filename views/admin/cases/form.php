<?php require __DIR__ . '/../_nav.php'; ?>
<section class="note">
    <h2><?= e($title) ?></h2>
    <p class="muted">Se clonan los pasos del protocolo de la certificación. El caso arranca en el paso 1.</p>
    <form method="post" action="/admin/cases/save" class="stack form-grid">
        <label>Certificación
            <select name="certification_id" required>
                <option value="">—</option>
                <?php foreach ($certifications as $c): ?>
                    <option value="<?= (int)$c['id'] ?>">
                        <?= e($c['code']) ?> · <?= e($c['name']) ?>
                        <?= empty($c['protocol_id']) ? ' (sin protocolo)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nombre del alumno<input name="student_name" required></label>
        <label>Correo del alumno<input type="email" name="student_email" required></label>
        <label>Fecha de examen (si ya se eligió)<input type="date" name="exam_date"></label>
        <label>Notas<textarea name="notes" rows="3"></textarea></label>
        <div class="actions">
            <button class="btn" type="submit">Abrir caso</button>
            <a class="btn btn-ghost" href="/admin/cases">Cancelar</a>
        </div>
    </form>
</section>

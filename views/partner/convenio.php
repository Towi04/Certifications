<?php
$statusLabels = [
    'pending' => 'Pendiente de firma',
    'submitted' => 'En revisión por Doceo',
    'approved' => 'Confirmado',
    'rejected' => 'Rechazado — vuelve a subir',
    'expired' => 'Plazo vencido — sube el firmado',
];
$st = (string)($assignment['signature_status'] ?? '');
$canUpload = in_array($st, ['pending', 'rejected', 'expired', 'submitted'], true);
?>
<section class="page-head">
    <div>
        <h1>Convenio Teacher Referral</h1>
        <p class="muted">
            <?= e($partner['organization'] ?: ($user['name'] ?? '')) ?>
            · <?= e($partner['tier_name'] ?? 'Sin nivel') ?>
        </p>
    </div>
    <a class="btn btn-ghost" href="/partner">Volver al catálogo</a>
</section>

<?php if (!empty($error)): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<?php if (!empty($info)): ?><p class="info"><?= e($info) ?></p><?php endif; ?>

<?php if (empty($canRegister)): ?>
<section class="note">
    <p>
        Tu cuenta puede iniciar sesión, pero el <strong>registro de alumnos está bloqueado</strong>
        hasta que Doceo confirme tu convenio firmado.
        <?php if (!empty($partner['restriction_reason'])): ?>
            <br><span class="muted"><?= e($partner['restriction_reason']) ?></span>
        <?php endif; ?>
    </p>
</section>
<?php endif; ?>

<?php if (!$assignment): ?>
<section class="note">
    <p class="muted">No hay un convenio asignado todavía. Contacta a Doceo si crees que es un error.</p>
</section>
<?php else: ?>
<section class="note stack">
    <h2><?= e($assignment['agreement_name'] ?? 'Convenio') ?> (<?= (int)($assignment['year'] ?? 0) ?>)</h2>
    <p>
        Estado: <strong><?= e($statusLabels[$st] ?? $st) ?></strong>
        <?php if (!empty($assignment['deadline_at'])): ?>
            · Plazo: <?= e(date('d/m/Y', strtotime((string)$assignment['deadline_at'])) ?: $assignment['deadline_at']) ?>
        <?php endif; ?>
    </p>
    <?php if (!empty($assignment['reject_reason'])): ?>
        <p class="error">Motivo: <?= e($assignment['reject_reason']) ?></p>
    <?php endif; ?>

    <?php if (!empty($assignment['blank_pdf_path'])): ?>
        <p>
            <a class="btn" href="/media?f=<?= e(rawurlencode($assignment['blank_pdf_path'])) ?>" target="_blank" rel="noopener">
                Descargar plantilla PDF
            </a>
        </p>
    <?php endif; ?>

    <?php if (!empty($assignment['signed_path'])): ?>
        <p class="muted">
            Último archivo enviado:
            <a href="/media?f=<?= e(rawurlencode($assignment['signed_path'])) ?>" target="_blank" rel="noopener">ver PDF</a>
        </p>
    <?php endif; ?>

    <?php if ($canUpload && $st !== 'approved'): ?>
        <form method="post" action="/partner/convenio/upload" enctype="multipart/form-data" class="stack form-grid">
            <input type="hidden" name="assignment_id" value="<?= (int)$assignment['id'] ?>">
            <label class="field-wide">PDF firmado
                <input type="file" name="signed_pdf" accept=".pdf,application/pdf" required>
            </label>
            <div class="actions">
                <button class="btn" type="submit">
                    <?= $st === 'submitted' ? 'Reemplazar PDF enviado' : 'Subir convenio firmado' ?>
                </button>
            </div>
        </form>
    <?php elseif ($st === 'approved'): ?>
        <p class="info">Convenio confirmado. Tu acceso completo está activo.</p>
    <?php endif; ?>
</section>
<?php endif; ?>

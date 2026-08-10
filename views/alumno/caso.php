<?php
$item = $item ?? [];
$steps = $steps ?? [];
$attachments = $attachments ?? [];
$regulation = $regulation ?? null;
$requires_regulation = !empty($requires_regulation);
$cenni_statuses = $cenni_statuses ?? [];

$paid = !empty($item['payment_confirmed_at']) || in_array(strtolower((string)($item['openpay_status'] ?? '')), ['completed', 'paid'], true);
$signed = !empty($item['regulation_signed_at']);
$needsSign = ($requires_regulation || $regulation) && !$signed;
$hasAccess = trim((string) ($item['access_key'] ?? '')) !== '' || trim((string) ($item['folio_id'] ?? '')) !== '';
$cenniProcess = (string) ($item['cenni_process'] ?? 'none');
$cenniStatus = (string) ($item['cenni_status'] ?? 'none');
$fullName = trim(
    (string) ($item['student_name'] ?? '') . ' '
    . (string) ($item['student_last_name_p'] ?? '') . ' '
    . (string) ($item['student_last_name_m'] ?? '')
);

// Progreso simple para el alumno (no el protocolo completo)
$checklist = [
    ['key' => 'datos', 'label' => 'Datos del candidato', 'done' => true],
    ['key' => 'reglamento', 'label' => 'Firma del reglamento', 'done' => !$needsSign],
    ['key' => 'pago', 'label' => 'Pago SPEI', 'done' => $paid],
    ['key' => 'examen', 'label' => 'Acceso al examen', 'done' => $hasAccess],
    ['key' => 'cenni', 'label' => 'Seguimiento certificado / CENNI', 'done' => $cenniStatus === 'issued'],
];
?>
<section class="page-head">
    <div>
        <h1><?= e($item['certification_name'] ?? ('Caso #' . ($item['id'] ?? ''))) ?></h1>
        <p class="muted">Candidato: <strong><?= e($fullName) ?></strong>
            <?php if (!empty($item['exam_date'])): ?> · Fecha solicitada: <?= e($item['exam_date']) ?><?php endif; ?>
            <?php if (!empty($item['exam_time'])): ?> · Hora: <?= e($item['exam_time']) ?><?php endif; ?>
            <?php if (!empty($item['exam_extraordinary'])): ?> · <span class="pill">Aplicación extraordinaria</span><?php endif; ?>
        </p>
    </div>
    <a class="btn btn-ghost" href="/alumno">Mis certificaciones</a>
</section>

<?php if (!empty($info)): ?><div class="alert alert-ok"><?= e($info) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<section class="note student-progress">
    <h2>Tu avance</h2>
    <ol class="student-checklist">
        <?php foreach ($checklist as $i => $row): ?>
            <li class="<?= $row['done'] ? 'is-done' : 'is-todo' ?>">
                <span class="student-check-num"><?= $i + 1 ?></span>
                <span><?= e($row['label']) ?></span>
                <span class="pill"><?= $row['done'] ? 'Listo' : 'Pendiente' ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<?php if ($needsSign): ?>
<section class="note student-stage" id="reglamento">
    <h2>1. Firma el reglamento</h2>
    <p>Debes leer y aceptar el reglamento del examen antes de continuar.</p>
    <?php if ($regulation && !empty($regulation['file_path'])): ?>
        <p>
            <a class="btn btn-ghost" href="/media?f=<?= e(rawurlencode((string)$regulation['file_path'])) ?>" target="_blank" rel="noopener">
                Abrir reglamento (PDF)<?= !empty($regulation['version']) ? ' · v' . e((string)$regulation['version']) : '' ?>
            </a>
        </p>
    <?php else: ?>
        <p class="muted">El reglamento aún no está cargado. Si el botón de firma no aparece disponible, contacta a Instituto Doceo.</p>
    <?php endif; ?>
    <form method="post" action="/alumno/caso/sign-regulation" class="stack form-grid">
        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
        <label class="check field-wide">
            <input type="checkbox" name="accept_regulation" value="1" required>
            He leído el reglamento y acepto sus términos.
        </label>
        <label class="field-wide">Nombre completo para firma
            <input name="signer_name" required value="<?= e($fullName) ?>"
                   placeholder="Debe coincidir con tu identificación">
        </label>
        <div class="actions">
            <button class="btn" type="submit" <?= ($regulation || !$requires_regulation) ? '' : 'disabled' ?>>Firmar reglamento</button>
        </div>
    </form>
</section>
<?php elseif ($signed): ?>
<section class="note">
    <p class="alert alert-ok">
        Reglamento firmado<?= !empty($item['regulation_signed_at']) ? ' el ' . e($item['regulation_signed_at']) : '' ?>
        por <?= e($item['regulation_signer_name'] ?? '') ?>.
    </p>
</section>
<?php endif; ?>

<?php if (!$needsSign): ?>
<section class="note student-stage" id="pago">
    <h2><?= $paid ? 'Pago' : '2. Realiza tu pago SPEI' ?></h2>
    <?php if ($paid): ?>
        <p class="alert alert-ok">Pago confirmado<?= !empty($item['openpay_paid_at']) ? ' el ' . e($item['openpay_paid_at']) : (!empty($item['payment_confirmed_at']) ? ' el ' . e($item['payment_confirmed_at']) : '') ?>.</p>
    <?php elseif (!empty($item['openpay_clabe'])): ?>
        <p>Usa estos datos para transferir. OpenPay confirmará el pago automáticamente.</p>
        <ul>
            <li><strong>Beneficiario:</strong> <?= e(\App\Config\Env::get('OPENPAY_BENEFICIARY_NAME', 'Instituto DOCEO') ?? 'Instituto DOCEO') ?></li>
            <li><strong>Banco:</strong> <?= e($item['openpay_bank'] ?? 'BBVA Bancomer') ?></li>
            <li><strong>CLABE:</strong> <code><?= e($item['openpay_clabe']) ?></code></li>
            <li><strong>Convenio / referencia:</strong> <?= e($item['openpay_reference'] ?? $item['openpay_agreement'] ?? '') ?></li>
            <li><strong>Monto:</strong> $<?= e(number_format((float)($item['openpay_amount'] ?? 0), 2)) ?> MXN</li>
        </ul>
        <p class="actions">
            <a class="btn" href="/pago/spei?id=<?= (int)$item['id'] ?>">Ver ficha SPEI Doceo</a>
            <?php if (!empty($item['openpay_pdf_url'])): ?>
                <a class="btn btn-ghost" href="<?= e($item['openpay_pdf_url']) ?>" target="_blank" rel="noopener">PDF OpenPay</a>
            <?php endif; ?>
        </p>
    <?php else: ?>
        <p class="muted">Aún no hay CLABE generada. El equipo Doceo la habilitará en breve.</p>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($paid): ?>
<section class="note student-stage" id="examen">
    <h2>3. Acceso a tu examen</h2>
    <?php if ($hasAccess): ?>
        <ul>
            <?php if (!empty($item['folio_id'])): ?><li><strong>ID / Folio:</strong> <code><?= e($item['folio_id']) ?></code></li><?php endif; ?>
            <?php if (!empty($item['access_key'])): ?><li><strong>Clave / código:</strong> <code><?= e($item['access_key']) ?></code></li><?php endif; ?>
            <?php if (!empty($item['exam_date'])): ?><li><strong>Fecha:</strong> <?= e($item['exam_date']) ?><?= !empty($item['exam_time']) ? ' · ' . e($item['exam_time']) : '' ?></li><?php endif; ?>
            <?php if (!empty($item['zoom_url'])): ?><li><strong>Zoom:</strong> <a href="<?= e($item['zoom_url']) ?>" target="_blank" rel="noopener">Abrir enlace</a></li><?php endif; ?>
        </ul>
    <?php else: ?>
        <p>
            Un día antes de tu examen te enviaremos el código de acceso.
            También puedes entrar a esta cuenta para revisar si ya fue asignado.
        </p>
        <p class="muted">Mientras tanto no necesitas hacer nada más aquí.</p>
    <?php endif; ?>
</section>

<section class="note student-stage" id="cenni">
    <h2>4. Certificado y trámite CENNI</h2>
    <?php if ($cenniProcess === 'uks_external'): ?>
        <p>
            Después de presentar el ELET recibirás tu constancia y un enlace/QR para subir INE, CURP y solicitud
            <strong>en la plataforma UKS</strong>. Aquí verás el avance que monitoreamos.
        </p>
        <p>
            Estatus actual:
            <strong><?= e($cenni_statuses[$cenniStatus] ?? $cenniStatus) ?></strong>
            <?php if (!empty($item['cenni_folio'])): ?> · Folio: <?= e($item['cenni_folio']) ?><?php endif; ?>
        </p>
    <?php elseif ($cenniProcess === 'doceo_managed'): ?>
        <p>Sube tus documentos para que Instituto Doceo gestione el trámite ante la SEP.</p>
        <p class="muted">Estatus: <strong><?= e($cenni_statuses[$cenniStatus] ?? $cenniStatus) ?></strong></p>
        <form method="post" action="/alumno/caso/upload-cenni" enctype="multipart/form-data" class="stack">
            <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
            <label>INE (PDF/imagen)<input type="file" name="cenni_ine" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
            <label>CURP (PDF/imagen)<input type="file" name="cenni_curp" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
            <label>Solicitud CENNI firmada (PDF)<input type="file" name="cenni_solicitud" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
            <button class="btn" type="submit">Subir documentos</button>
        </form>
    <?php else: ?>
        <p class="muted">Esta certificación no incluye trámite CENNI en plataforma. Te avisaremos del estatus de tu certificado por correo.</p>
    <?php endif; ?>

    <?php
    $cenniAtt = array_filter($attachments, static fn ($a) => in_array($a['kind'] ?? '', ['ine', 'curp', 'cenni'], true));
    if ($cenniAtt):
    ?>
        <h3>Documentos enviados</h3>
        <ul>
            <?php foreach ($cenniAtt as $att): ?>
                <li><?= e($att['label'] ?? $att['kind']) ?> · <?= e($att['created_at'] ?? '') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php endif; ?>

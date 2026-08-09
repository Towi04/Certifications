<?php
$item = $item ?? [];
$steps = $steps ?? [];
$stages = $stages ?? [];
$regulation = $regulation ?? null;
$user = $user ?? [];
$signed = !empty($item['regulation_signed_at']);
$paid = !empty($item['payment_confirmed_at']);
$paymentUrl = trim((string) ($item['payment_link_url'] ?? ''));
$hasAccess = trim((string) ($item['folio_id'] ?? '')) !== ''
    || trim((string) ($item['access_key'] ?? '')) !== '';
$fullName = trim(
    (string) ($item['student_name'] ?? '') . ' '
    . (string) ($item['student_last_name_p'] ?? '') . ' '
    . (string) ($item['student_last_name_m'] ?? '')
);
$postSteps = array_values(array_filter($steps, static fn ($s) => ($s['phase'] ?? '') === 'post_exam'));
$statusLabels = [
    'pending' => 'Pendiente',
    'current' => 'En curso',
    'done' => 'Listo',
    'skipped' => 'Omitido',
    'blocked' => 'Bloqueado',
];
?>
<section class="page-head">
    <div>
        <h1><?= e($item['certification_name'] ?? ('Caso #' . ($item['id'] ?? ''))) ?></h1>
        <p class="muted">
            <?= e($fullName) ?>
            <?php if (!empty($item['exam_date'])): ?>
                · Examen: <?= e($item['exam_date']) ?><?= !empty($item['exam_time']) ? ' ' . e($item['exam_time']) : '' ?>
            <?php endif; ?>
        </p>
    </div>
    <a class="btn btn-ghost" href="/alumno">Mis certificaciones</a>
</section>

<?php if (!empty($info)): ?><div class="alert alert-ok"><?= e($info) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<section class="note">
    <h2 style="margin-top:0">Tu avance</h2>
    <p class="muted">Vas completando cada etapa. No necesitas ver todo el protocolo interno: solo lo que te corresponde ahora.</p>
    <ol class="student-stages">
        <?php foreach ($stages as $stage): ?>
            <li class="student-stage status-<?= e($stage['status']) ?>">
                <strong><?= e($stage['label']) ?></strong>
                <span class="pill"><?= e($statusLabels[$stage['status']] ?? $stage['status']) ?></span>
                <span class="muted"><?= e($stage['hint']) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<?php if (!$signed): ?>
<section class="note student-action" id="reglamento">
    <h2>1. Firma el reglamento</h2>
    <p class="muted">Debes aceptar el reglamento del examen antes de continuar. Usa el mismo nombre que capturaste para tu certificación.</p>

    <?php if ($regulation && !empty($regulation['file_path'])): ?>
        <p>
            <a class="btn btn-ghost" href="/media?f=<?= e(rawurlencode($regulation['file_path'])) ?>" target="_blank" rel="noopener">
                Ver reglamento<?= !empty($regulation['version']) ? ' v' . e($regulation['version']) : '' ?>
            </a>
        </p>
    <?php else: ?>
        <div class="prose regulation-fallback">
            <p>Al firmar confirmas que:</p>
            <ul>
                <li>Los datos proporcionados son correctos y serán los de tu certificación.</li>
                <li>Te presentaras en la fecha/hora acordadas con identificación oficial.</li>
                <li>Cumplirás las reglas de aplicación (sin ayudas no autorizadas, cámara/micrófono si aplica, etc.).</li>
                <li>Conoces las políticas de reagenda y el trámite CENNI cuando aplique.</li>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/alumno/caso/firmar-reglamento" class="stack form-grid">
        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
        <label class="check field-wide">
            <input type="checkbox" name="accept_regulation" value="1" required>
            He leído y acepto el reglamento del examen
        </label>
        <label class="field-wide">Nombre completo (firma)
            <input name="signer_name" required value="<?= e($fullName) ?>"
                   placeholder="Debe coincidir con tu registro">
        </label>
        <div class="actions">
            <button class="btn" type="submit">Firmar reglamento</button>
        </div>
    </form>
</section>
<?php else: ?>
<section class="note">
    <h2>Reglamento</h2>
    <p class="alert alert-ok" style="margin:0">
        Firmado el <?= e($item['regulation_signed_at']) ?>
        por <strong><?= e($item['regulation_signer_name'] ?? $fullName) ?></strong>.
    </p>
</section>
<?php endif; ?>

<section class="note student-action" id="pago">
    <h2><?= $signed ? '2' : '2' ?>. Pago OpenPay</h2>
    <?php if ($paid): ?>
        <p class="alert alert-ok" style="margin:0">
            Pago confirmado<?= !empty($item['payment_confirmed_at']) ? ' el ' . e($item['payment_confirmed_at']) : '' ?>.
        </p>
    <?php else: ?>
        <p class="muted">
            Realiza el pago con el link de OpenPay. Cuando el equipo lo confirme, prepararemos tu examen con la certificadora.
        </p>
        <?php if ($paymentUrl !== ''): ?>
            <div class="actions">
                <?php if (str_starts_with($paymentUrl, 'http')): ?>
                    <a class="btn" href="<?= e($paymentUrl) ?>" target="_blank" rel="noopener">Ir a pagar con OpenPay</a>
                <?php else: ?>
                    <a class="btn" href="<?= e($paymentUrl) ?>">Ver instrucciones de pago</a>
                <?php endif; ?>
            </div>
            <?php if (!str_starts_with($paymentUrl, 'http')): ?>
                <p class="muted">
                    Si aún no ves un link externo, el equipo te compartirá el link OpenPay o lo configurará en cuanto esté disponible.
                    Precio de referencia:
                    <?php
                    // public_price may not be on case; show soft message
                    ?>
                    el de la ficha del producto.
                </p>
            <?php endif; ?>
        <?php else: ?>
            <p class="muted">El link de pago se está generando. Si no aparece en unos minutos, escríbenos.</p>
        <?php endif; ?>

        <form method="post" action="/alumno/caso/confirmar-pago-iniciado" class="stack" style="margin-top:1rem">
            <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
            <button class="btn btn-ghost" type="submit">Ya realicé / inicié el pago</button>
        </form>
    <?php endif; ?>
</section>

<section class="note" id="acceso">
    <h2>Código de acceso al examen</h2>
    <?php if ($hasAccess): ?>
        <div class="facts">
            <?php if (!empty($item['folio_id'])): ?>
                <p><strong>Folio / ID:</strong> <?= e($item['folio_id']) ?></p>
            <?php endif; ?>
            <?php if (!empty($item['access_key'])): ?>
                <p><strong>Clave:</strong> <?= e($item['access_key']) ?></p>
            <?php endif; ?>
            <?php if (!empty($item['access_doc_url'])): ?>
                <p><a href="<?= e($item['access_doc_url']) ?>" target="_blank" rel="noopener">Guía / documento de acceso</a></p>
            <?php endif; ?>
            <?php if (!empty($item['zoom_url'])): ?>
                <p><strong>Zoom:</strong> <a href="<?= e($item['zoom_url']) ?>" target="_blank" rel="noopener"><?= e($item['zoom_url']) ?></a></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="muted">
            Un día antes de tu examen te enviaremos el código de acceso.
            También puedes volver a esta página para ver si ya fue asignado.
        </p>
    <?php endif; ?>
</section>

<?php if ($postSteps): ?>
<section class="note" id="cenni">
    <h2>Certificado y trámite CENNI</h2>
    <p class="muted">Después de presentar el examen podrás dar seguimiento aquí al estado de tu certificado y del trámite CENNI.</p>
    <ul class="student-cenni-list">
        <?php foreach ($postSteps as $step): ?>
            <li class="status-<?= e($step['status']) ?>">
                <strong><?= e($step['title']) ?></strong>
                <span class="pill"><?= e($statusLabels[$step['status']] ?? $step['status']) ?></span>
                <?php if (!empty($step['description'])): ?>
                    <span class="muted"><?= e($step['description']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

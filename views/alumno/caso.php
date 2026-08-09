<?php
$item = $item ?? [];
$steps = $steps ?? [];
$phases = $phases ?? [];
$responsibles = $responsibles ?? [];
$attachments = $attachments ?? [];
$cenni_statuses = $cenni_statuses ?? [];
$statusLabels = [
    'pending' => 'Pendiente',
    'current' => 'En curso',
    'done' => 'Hecho',
    'skipped' => 'Omitido',
    'blocked' => 'Bloqueado',
];
$paid = !empty($item['payment_confirmed_at']) || in_array(strtolower((string)($item['openpay_status'] ?? '')), ['completed', 'paid'], true);
$cenniProcess = (string) ($item['cenni_process'] ?? 'none');
$cenniStatus = (string) ($item['cenni_status'] ?? 'none');
?>
<section class="page-head">
    <div>
        <h1><?= e($item['certification_name'] ?? ('Caso #' . ($item['id'] ?? ''))) ?></h1>
        <p class="muted">
            Protocolo: <?= e($item['protocol_name'] ?? '') ?> ·
            Estado: <?= e($item['status'] ?? '') ?>
        </p>
    </div>
    <a class="btn btn-ghost" href="/alumno">Mis certificaciones</a>
</section>

<?php if (!empty($info)): ?><div class="alert alert-ok"><?= e($info) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<section class="note">
    <h2>Pago</h2>
    <?php if ($paid): ?>
        <p class="alert alert-ok">Pago confirmado<?= !empty($item['openpay_paid_at']) ? ' el ' . e($item['openpay_paid_at']) : (!empty($item['payment_confirmed_at']) ? ' el ' . e($item['payment_confirmed_at']) : '') ?>.</p>
    <?php elseif (!empty($item['openpay_clabe'])): ?>
        <p>Realiza una transferencia SPEI con estos datos. OpenPay confirmará el pago automáticamente.</p>
        <ul>
            <li><strong>Banco:</strong> <?= e($item['openpay_bank'] ?? 'BBVA Bancomer') ?></li>
            <li><strong>CLABE:</strong> <code><?= e($item['openpay_clabe']) ?></code></li>
            <li><strong>Convenio / referencia:</strong> <?= e($item['openpay_reference'] ?? $item['openpay_agreement'] ?? '') ?></li>
            <li><strong>Monto:</strong> $<?= e(number_format((float)($item['openpay_amount'] ?? 0), 2)) ?> MXN</li>
        </ul>
        <?php if (!empty($item['openpay_pdf_url'])): ?>
            <p><a class="btn btn-ghost" href="<?= e($item['openpay_pdf_url']) ?>" target="_blank" rel="noopener">Descargar ficha SPEI (PDF)</a></p>
        <?php endif; ?>
    <?php else: ?>
        <p class="muted">Aún no hay CLABE generada. Si acabas de adquirir, espera un momento o contacta a Instituto Doceo.</p>
    <?php endif; ?>
</section>

<?php if ($cenniProcess === 'uks_external'): ?>
<section class="note">
    <h2>Trámite CENNI (ELET / UKS)</h2>
    <p>
        Al terminar tu examen ELET recibes tu constancia y un enlace (o QR) para subir INE, CURP y solicitud
        <strong>directamente en la plataforma UKS</strong>. No es necesario subirlos aquí.
    </p>
    <p class="muted">
        Estatus que monitoreamos en Doceo:
        <strong><?= e($cenni_statuses[$cenniStatus] ?? $cenniStatus) ?></strong>
        <?php if (!empty($item['cenni_folio'])): ?> · Folio: <?= e($item['cenni_folio']) ?><?php endif; ?>
    </p>
    <p class="muted">UKS y la SEP también te avisarán por correo; nosotros te iremos informando cada avance desde esta plataforma.</p>
</section>
<?php elseif ($cenniProcess === 'doceo_managed'): ?>
<section class="note">
    <h2>Documentos CENNI (gestión Doceo)</h2>
    <p>Sube aquí tus documentos para que Instituto Doceo gestione el trámite ante la SEP.</p>
    <p class="muted">Estatus: <strong><?= e($cenni_statuses[$cenniStatus] ?? $cenniStatus) ?></strong></p>
    <form method="post" action="/alumno/caso/upload-cenni" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
        <label>INE (PDF/imagen)<input type="file" name="cenni_ine" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
        <label>CURP (PDF/imagen)<input type="file" name="cenni_curp" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
        <label>Solicitud CENNI firmada (PDF)<input type="file" name="cenni_solicitud" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
        <button class="btn" type="submit">Subir documentos</button>
    </form>
</section>
<?php endif; ?>

<?php
$cenniAtt = array_filter($attachments, static fn ($a) => in_array($a['kind'] ?? '', ['ine', 'curp', 'cenni'], true));
if ($cenniAtt):
?>
<section class="note">
    <h3>Tus documentos enviados</h3>
    <ul>
        <?php foreach ($cenniAtt as $att): ?>
            <li><?= e($att['label'] ?? $att['kind']) ?> · <?= e($att['created_at'] ?? '') ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="note">
    <p class="muted">Este timeline te muestra en qué paso va tu certificación. Algunos pasos los completa el equipo Doceo, el TR o la certificadora.</p>
    <ol class="protocol-timeline protocol-timeline--progress">
        <?php
        $lastPhase = null;
        foreach ($steps as $step):
            $phase = (string) $step['phase'];
            if ($phase !== $lastPhase):
                $lastPhase = $phase;
        ?>
            <li class="protocol-phase-label"><?= e($phases[$phase] ?? $phase) ?></li>
        <?php endif; ?>
            <li class="protocol-step status-<?= e($step['status']) ?>">
                <div class="protocol-step-head">
                    <span class="protocol-step-num"><?= (int)$step['sort_order'] ?></span>
                    <strong><?= e($step['title']) ?></strong>
                    <span class="pill"><?= e($statusLabels[$step['status']] ?? $step['status']) ?></span>
                    <span class="pill"><?= e($responsibles[$step['responsible']] ?? $step['responsible']) ?></span>
                </div>
                <?php if (!empty($step['description'])): ?>
                    <p class="muted"><?= e($step['description']) ?></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

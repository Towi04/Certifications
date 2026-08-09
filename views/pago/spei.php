<?php
$item = $item ?? [];
$beneficiary = $beneficiary ?? 'Instituto DOCEO';
$amount = number_format((float) ($item['openpay_amount'] ?? 0), 2);
$dueRaw = (string) ($item['openpay_due_at'] ?? '');
$dueLabel = $dueRaw !== '' ? date('d/m/Y H:i', strtotime($dueRaw)) : '—';
$clabe = (string) ($item['openpay_clabe'] ?? '');
$bank = (string) ($item['openpay_bank'] ?? 'BBVA Bancomer');
$reference = (string) ($item['openpay_reference'] ?? $item['openpay_agreement'] ?? '');
$agreement = (string) ($item['openpay_agreement'] ?? '');
$concept = trim('Certificación ' . (string) ($item['certification_name'] ?? $item['certification_code'] ?? '') . ' — caso #' . (string) ($item['id'] ?? ''));
$sandbox = filter_var(\App\Config\Env::get('OPENPAY_SANDBOX', 'true') ?? 'true', FILTER_VALIDATE_BOOLEAN);
?>
<article class="spei-voucher">
    <header class="spei-voucher__head">
        <img class="spei-voucher__logo" src="/assets/brand/logo-doceo.svg" width="280" height="93" alt="Instituto DOCEO">
        <div class="spei-voucher__meta">
            <p class="eyebrow">Ficha de pago SPEI</p>
            <p class="muted">Transferencia interbancaria</p>
        </div>
    </header>

    <?php if ($sandbox): ?>
        <div class="alert alert-error spei-voucher__sandbox">
            Entorno de pruebas (sandbox OpenPay). La CLABE y el convenio son de prueba: un pago real no liquidará este cargo.
        </div>
    <?php endif; ?>

    <div class="spei-voucher__grid">
        <div>
            <p class="spei-label">Fecha límite de pago</p>
            <p class="spei-value"><?= e($dueLabel) ?></p>
            <p class="spei-label">Beneficiario</p>
            <p class="spei-value spei-value--strong"><?= e($beneficiary) ?></p>
            <p class="spei-label">Banco</p>
            <p class="spei-value"><?= e($bank) ?></p>
            <p class="spei-label">CLABE</p>
            <p class="spei-value spei-mono"><?= e($clabe) ?></p>
            <?php if ($agreement !== ''): ?>
                <p class="spei-label">Número de convenio CIE</p>
                <p class="spei-value spei-mono"><?= e($agreement) ?></p>
            <?php endif; ?>
            <p class="spei-label">Referencia</p>
            <p class="spei-value spei-mono"><?= e($reference) ?></p>
            <p class="spei-label">Concepto</p>
            <p class="spei-value"><?= e($concept) ?></p>
        </div>
        <aside class="spei-amount">
            <p class="spei-amount__label">Total a pagar / MXN</p>
            <p class="spei-amount__value">$<?= e($amount) ?></p>
        </aside>
    </div>

    <ol class="spei-steps">
        <li>Ingresa a tu banca en línea o app y elige <strong>transferencia SPEI</strong> / pago a convenio CIE.</li>
        <li>Captura la <strong>CLABE</strong> o el <strong>convenio CIE</strong> y la <strong>referencia</strong> exactamente como aparecen aquí.</li>
        <li>Confirma el importe <strong>$<?= e($amount) ?> MXN</strong>. OpenPay avisará automáticamente cuando se acredite.</li>
    </ol>

    <div class="spei-actions no-print">
        <button class="btn" type="button" onclick="window.print()">Imprimir / guardar PDF</button>
        <?php if (!empty($item['openpay_pdf_url'])): ?>
            <a class="btn btn-ghost" href="<?= e($item['openpay_pdf_url']) ?>" target="_blank" rel="noopener">PDF OpenPay</a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="/alumno/caso?id=<?= (int) ($item['id'] ?? 0) ?>">Volver al caso</a>
    </div>
</article>

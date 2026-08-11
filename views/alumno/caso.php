<?php
$item = $item ?? [];
$steps = $steps ?? [];
$attachments = $attachments ?? [];
$regulation = $regulation ?? null;
$requires_regulation = !empty($requires_regulation);
$cenni_statuses = $cenni_statuses ?? [];
$cenni_docs = $cenni_docs ?? [];

$paid = !empty($item['payment_confirmed_at']) || in_array(strtolower((string)($item['openpay_status'] ?? '')), ['completed', 'paid'], true);
$signed = !empty($item['regulation_signed_at']);
$needsSign = ($requires_regulation || $regulation) && !$signed;
$hasAccess = trim((string) ($item['access_key'] ?? '')) !== '' || trim((string) ($item['folio_id'] ?? '')) !== '';
$hasMoodle = trim((string) ($item['moodle_user'] ?? '')) !== '';
$examOutcome = (string) ($item['exam_outcome'] ?? 'pending');
$hasResults = $examOutcome === 'delivered'
    || trim((string) ($item['results_url'] ?? '')) !== ''
    || trim((string) ($item['score_url'] ?? '')) !== ''
    || trim((string) ($item['certificate_url'] ?? '')) !== '';
$isInvalidated = $examOutcome === 'invalidated';
$cenniProcess = (string) ($item['cenni_process'] ?? 'none');
$cenniStatus = (string) ($item['cenni_status'] ?? 'none');
$fullName = trim(
    (string) ($item['student_name'] ?? '') . ' '
    . (string) ($item['student_last_name_p'] ?? '') . ' '
    . (string) ($item['student_last_name_m'] ?? '')
);
$paymentMethod = trim((string) ($item['payment_method'] ?? ''));
$paymentMethodLabel = match ($paymentMethod) {
    'cash' => 'Efectivo',
    'transfer' => 'Transferencia bancaria',
    'openpay' => 'OpenPay SPEI',
    'other' => 'Otro',
    default => '',
};

// Timeline del alumno (flujo visual, no el protocolo interno completo)
$timeline = [
    [
        'key' => 'datos',
        'label' => 'Registro',
        'hint' => 'Datos del candidato listos',
        'done' => true,
    ],
    [
        'key' => 'reglamento',
        'label' => 'Firma del reglamento',
        'hint' => $signed
            ? ('Firmado' . (!empty($item['regulation_signed_at']) ? ' · ' . $item['regulation_signed_at'] : ''))
            : 'Dibuja o escribe tu firma digital',
        'done' => $signed || !$needsSign,
    ],
    [
        'key' => 'pago',
        'label' => 'Pago',
        'hint' => $paid
            ? ('Confirmado' . ($paymentMethodLabel !== '' ? ' · ' . $paymentMethodLabel : '')
                . (!empty($item['payment_confirmed_at']) ? ' · ' . $item['payment_confirmed_at'] : ''))
            : (trim((string) ($item['payment_proof_path'] ?? '')) !== ''
                ? 'Comprobante enviado · pendiente de confirmación Doceo'
                : 'Pendiente de pago (SPEI, efectivo o transferencia)'),
        'done' => $paid,
    ],
    [
        'key' => 'examen',
        'label' => 'Acceso al examen',
        'hint' => $hasAccess ? 'Credenciales listas' : 'Se publican cerca de tu fecha',
        'done' => $hasAccess,
    ],
    [
        'key' => 'resultados',
        'label' => 'Resultados',
        'hint' => $isInvalidated
            ? 'Examen invalidado'
            : ($hasResults ? 'Enlaces disponibles' : 'Pendiente de publicación'),
        'done' => $hasResults || $isInvalidated,
    ],
    [
        'key' => 'cenni',
        'label' => 'Certificado / CENNI',
        'hint' => $cenni_statuses[$cenniStatus] ?? $cenniStatus,
        'done' => $cenniStatus === 'issued',
    ],
];

$currentKey = 'datos';
foreach ($timeline as $row) {
    if (!$row['done']) {
        $currentKey = $row['key'];
        break;
    }
    $currentKey = $row['key'];
}
if ($needsSign) {
    $currentKey = 'reglamento';
} elseif (!$paid) {
    $currentKey = 'pago';
} elseif (!$hasAccess) {
    $currentKey = 'examen';
} elseif (!$hasResults && !$isInvalidated) {
    $currentKey = 'resultados';
} elseif ($cenniStatus !== 'issued') {
    $currentKey = 'cenni';
}
?>
<section class="page-head">
    <div>
        <h1><?= e($item['certification_name'] ?? ('Caso #' . ($item['id'] ?? ''))) ?></h1>
        <p class="muted">Candidato: <strong><?= e($fullName) ?></strong>
            <?php if (!empty($item['exam_date'])): ?> · Fecha solicitada: <?= e($item['exam_date']) ?><?php endif; ?>
            <?php if (!empty($item['exam_time'])): ?> · Hora: <?= e($item['exam_time']) ?><?php endif; ?>
            <?php if (!empty($item['exam_extraordinary'])): ?> · <span class="pill">Aplicación extraordinaria</span><?php endif; ?>
        </p>
        <?php if ($signed && !$paid): ?>
            <p class="alert alert-warn" style="margin-top:0.75rem">
                Tu caso está <strong>pendiente de pago</strong>.
                <?php if (trim((string) ($item['payment_proof_path'] ?? '')) !== ''): ?>
                    Ya subiste comprobante; Doceo confirmará la recepción para que continues.
                <?php else: ?>
                    Puedes generar CLABE SPEI OpenPay o subir comprobante si ya pagaste en efectivo/transferencia.
                <?php endif; ?>
            </p>
        <?php elseif ($paid): ?>
            <p class="alert alert-ok" style="margin-top:0.75rem">Pago confirmado<?= $paymentMethodLabel !== '' ? ' (' . e($paymentMethodLabel) . ')' : '' ?>.</p>
        <?php endif; ?>
    </div>
    <a class="btn btn-ghost" href="/alumno">Mis certificaciones</a>
</section>

<?php if (!empty($info)): ?><div class="alert alert-ok"><?= e($info) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<section class="note student-progress">
    <h2>Tu avance</h2>
    <ol class="student-timeline">
        <?php
        $reachedCurrent = false;
        foreach ($timeline as $i => $row):
            $isCurrent = !$reachedCurrent && $row['key'] === $currentKey;
            if ($isCurrent) {
                $reachedCurrent = true;
            }
            $state = $row['done'] ? 'is-done' : ($isCurrent ? 'is-current' : 'is-todo');
            ?>
            <li class="student-timeline-item <?= $state ?>">
                <div class="student-timeline-rail" aria-hidden="true">
                    <span class="student-timeline-dot"><?= $row['done'] ? '✓' : ($i + 1) ?></span>
                </div>
                <div class="student-timeline-body">
                    <div class="student-timeline-head">
                        <strong><?= e($row['label']) ?></strong>
                        <span class="pill"><?= $row['done'] ? 'Listo' : ($isCurrent ? 'En curso' : 'Pendiente') ?></span>
                    </div>
                    <p class="muted student-timeline-hint"><?= e((string) $row['hint']) ?></p>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<?php if ($needsSign): ?>
<section class="note student-stage student-stage-active" id="reglamento">
    <h2>Firma el reglamento</h2>
    <p>Lee el PDF del reglamento y fírmalo aquí (dibujo o nombre escrito).</p>
    <?php if ($regulation && !empty($regulation['file_path'])): ?>
        <p class="actions">
            <a class="btn btn-ghost" href="/media?f=<?= e(rawurlencode((string)$regulation['file_path'])) ?>" target="_blank" rel="noopener">
                Abrir reglamento (PDF)<?= !empty($regulation['version']) ? ' · v' . e((string)$regulation['version']) : '' ?>
            </a>
        </p>
    <?php else: ?>
        <p class="muted">El reglamento aún no está cargado. Si el botón de firma no aparece disponible, contacta a Instituto Doceo.</p>
    <?php endif; ?>

    <form method="post" action="/alumno/caso/sign-regulation" class="stack" id="signRegulationForm" enctype="multipart/form-data">
        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
        <input type="hidden" name="signature_data" id="signatureData" value="">
        <input type="hidden" name="accept_regulation" value="1">

        <label>Nombre completo para la firma
            <input name="signer_name" id="signerName" required value="<?= e($fullName) ?>"
                   placeholder="Debe coincidir con tu identificación">
        </label>

        <div class="signature-mode-tabs" role="radiogroup" aria-label="Cómo firmar">
            <label class="signature-mode-tab">
                <input type="radio" name="signature_mode" value="draw" checked>
                <span>Dibujar mi firma</span>
            </label>
            <label class="signature-mode-tab">
                <input type="radio" name="signature_mode" value="type">
                <span>Nombre escrito</span>
            </label>
        </div>

        <div class="signature-pad-wrap" id="drawPadWrap">
            <p class="muted">Dibuja tu firma con el dedo o el mouse dentro del recuadro blanco.</p>
            <div class="signature-pad-frame">
                <canvas id="signaturePad" width="640" height="200" aria-label="Área para dibujar la firma"></canvas>
                <span class="signature-pad-hint" id="signaturePadHint">Firma aquí</span>
            </div>
            <div class="actions" style="margin-top:0.5rem">
                <button type="button" class="btn btn-ghost" id="clearSignature">Borrar firma</button>
            </div>
        </div>

        <div id="typePadWrap" hidden>
            <p class="muted">Tu nombre se usará como firma tipográfica.</p>
            <p class="signature-type-preview" id="typePreview"><?= e($fullName !== '' ? $fullName : 'Tu nombre') ?></p>
        </div>

        <div class="actions">
            <button class="btn" type="submit" id="signSubmit" <?= ($regulation || !$requires_regulation) ? '' : 'disabled' ?>>
                Firmar reglamento
            </button>
        </div>
    </form>
</section>
<script>
(function () {
  var canvas = document.getElementById('signaturePad');
  var form = document.getElementById('signRegulationForm');
  var dataInput = document.getElementById('signatureData');
  var drawWrap = document.getElementById('drawPadWrap');
  var typeWrap = document.getElementById('typePadWrap');
  var typePreview = document.getElementById('typePreview');
  var nameInput = document.getElementById('signerName');
  var hint = document.getElementById('signaturePadHint');
  var submitBtn = document.getElementById('signSubmit');
  if (!canvas || !form) return;

  var ctx = canvas.getContext('2d');
  var drawing = false;
  var hasInk = false;

  function resize() {
    var ratio = Math.max(window.devicePixelRatio || 1, 1);
    var frame = canvas.parentElement;
    var cssW = Math.max((frame && frame.clientWidth) || canvas.clientWidth || 640, 280);
    var cssH = 200;
    canvas.style.width = cssW + 'px';
    canvas.style.height = cssH + 'px';
    canvas.width = Math.floor(cssW * ratio);
    canvas.height = Math.floor(cssH * ratio);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.lineWidth = 2.4;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#1a1a1a';
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, cssW, cssH);
    hasInk = false;
    if (hint) hint.hidden = false;
  }

  function pos(e) {
    var r = canvas.getBoundingClientRect();
    var t = e.touches && e.touches[0] ? e.touches[0] : e;
    return { x: t.clientX - r.left, y: t.clientY - r.top };
  }
  function start(e) {
    e.preventDefault();
    drawing = true;
    var p = pos(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    if (hint) hint.hidden = true;
  }
  function move(e) {
    if (!drawing) return;
    e.preventDefault();
    var p = pos(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    hasInk = true;
  }
  function end() { drawing = false; }

  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);
  canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('touchmove', move, { passive: false });
  canvas.addEventListener('touchend', end);
  document.getElementById('clearSignature')?.addEventListener('click', resize);
  window.addEventListener('resize', resize);

  function mode() {
    var checked = form.querySelector('input[name="signature_mode"]:checked');
    return checked ? checked.value : 'draw';
  }
  function syncMode() {
    var m = mode();
    if (drawWrap) drawWrap.hidden = m !== 'draw';
    if (typeWrap) typeWrap.hidden = m !== 'type';
    form.querySelectorAll('.signature-mode-tab').forEach(function (tab) {
      var input = tab.querySelector('input');
      tab.classList.toggle('is-active', !!(input && input.checked));
    });
    if (m === 'draw') {
      requestAnimationFrame(resize);
    }
  }
  form.querySelectorAll('input[name="signature_mode"]').forEach(function (el) {
    el.addEventListener('change', syncMode);
  });
  nameInput?.addEventListener('input', function () {
    if (typePreview) typePreview.textContent = nameInput.value.trim() || 'Tu nombre';
  });
  syncMode();
  resize();

  /** Compacta el canvas a JPEG pequeño para no romper el POST del servidor. */
  function exportSignatureBlob() {
    return new Promise(function (resolve, reject) {
      var maxW = 480;
      var maxH = 150;
      var srcW = canvas.width;
      var srcH = canvas.height;
      if (!srcW || !srcH) {
        reject(new Error('Canvas vacío'));
        return;
      }
      var scale = Math.min(maxW / srcW, maxH / srcH, 1);
      var out = document.createElement('canvas');
      out.width = Math.max(1, Math.round(srcW * scale));
      out.height = Math.max(1, Math.round(srcH * scale));
      var octx = out.getContext('2d');
      octx.fillStyle = '#ffffff';
      octx.fillRect(0, 0, out.width, out.height);
      octx.drawImage(canvas, 0, 0, out.width, out.height);
      if (out.toBlob) {
        out.toBlob(function (blob) {
          if (!blob) {
            reject(new Error('No se pudo comprimir la firma'));
            return;
          }
          resolve(blob);
        }, 'image/jpeg', 0.72);
      } else {
        var dataUrl = out.toDataURL('image/jpeg', 0.72);
        resolve(dataUrl);
      }
    });
  }

  form.addEventListener('submit', function (e) {
    var m = mode();
    dataInput.value = '';
    if (m !== 'draw') {
      return;
    }
    e.preventDefault();
    if (!hasInk) {
      alert('Dibuja tu firma en el recuadro o elige “Nombre escrito”.');
      return;
    }
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Firmando…';
    }
    exportSignatureBlob().then(function (payload) {
      var fd = new FormData(form);
      fd.set('signature_mode', 'draw');
      fd.set('accept_regulation', '1');
      fd.delete('signature_data');
      if (typeof payload === 'string') {
        fd.set('signature_data', payload);
      } else {
        fd.set('signature_image', payload, 'firma.jpg');
      }
      return fetch(form.action, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        redirect: 'follow',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
    }).then(function (res) {
      if (res.redirected && res.url) {
        window.location.href = res.url;
        return;
      }
      var caseId = form.querySelector('input[name="case_id"]');
      window.location.href = '/alumno/caso?id=' + (caseId ? caseId.value : '');
    }).catch(function () {
      alert('No se pudo enviar la firma. Prueba de nuevo o usa “Nombre escrito”.');
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Firmar reglamento';
      }
    });
  });
})();
</script>
<?php elseif ($signed): ?>
<section class="note student-stage" id="reglamento-ok">
    <p class="alert alert-ok">
        Reglamento firmado digitalmente<?= !empty($item['regulation_signed_at']) ? ' el ' . e($item['regulation_signed_at']) : '' ?>
        por <?= e($item['regulation_signer_name'] ?? '') ?>
        <?= !empty($item['regulation_signature_mode']) ? ' · modo ' . e((string)$item['regulation_signature_mode']) : '' ?>.
    </p>
    <?php if (!empty($item['regulation_signed_pdf_path'])): ?>
        <p class="actions">
            <a class="btn" href="/media?f=<?= e(rawurlencode((string)$item['regulation_signed_pdf_path'])) ?>&download=1&name=reglamento-firmado-caso-<?= (int)$item['id'] ?>" target="_blank" rel="noopener">
                Descargar PDF firmado (evidencia)
            </a>
            <?php if (!empty($item['regulation_signature_path'])): ?>
                <a class="btn btn-ghost" href="/media?f=<?= e(rawurlencode((string)$item['regulation_signature_path'])) ?>" target="_blank" rel="noopener">Ver imagen de firma</a>
            <?php endif; ?>
            <?php if ($regulation && !empty($regulation['file_path'])): ?>
                <a class="btn btn-ghost" href="/media?f=<?= e(rawurlencode((string)$regulation['file_path'])) ?>" target="_blank" rel="noopener">Reglamento original</a>
            <?php endif; ?>
        </p>
        <p class="muted">El PDF incluye el reglamento que leíste más una hoja final con tu firma digital y los datos del sistema.</p>
    <?php else: ?>
        <p class="alert alert-warn">La firma quedó registrada, pero no hay PDF de evidencia. Contacta a Doceo para regenerarlo.</p>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!$needsSign): ?>
<section class="note student-stage <?= !$paid ? 'student-stage-active' : '' ?>" id="pago">
    <h2><?= $paid ? 'Pago' : 'Pendiente de pago' ?></h2>
    <?php
    $hasPaymentProof = trim((string) ($item['payment_proof_path'] ?? '')) !== '';
    ?>
    <?php if ($paid): ?>
        <p class="alert alert-ok">
            Pago confirmado
            <?= $paymentMethodLabel !== '' ? ' · ' . e($paymentMethodLabel) : '' ?>
            <?= !empty($item['openpay_paid_at']) ? ' el ' . e($item['openpay_paid_at']) : (!empty($item['payment_confirmed_at']) ? ' el ' . e($item['payment_confirmed_at']) : '') ?>.
        </p>
        <?php if ($hasPaymentProof): ?>
            <p class="actions">
                <a class="btn btn-ghost" href="/media?f=<?= e(rawurlencode((string)$item['payment_proof_path'])) ?>" target="_blank" rel="noopener">Ver comprobante enviado</a>
            </p>
        <?php endif; ?>
    <?php else: ?>
        <?php if ($hasPaymentProof): ?>
            <p class="alert alert-warn">
                Ya subiste tu comprobante. Instituto Doceo debe confirmar la recepción del pago para que el proceso continúe
                (Moodle, códigos de examen, etc.).
            </p>
            <p class="actions">
                <a class="btn btn-ghost" href="/media?f=<?= e(rawurlencode((string)$item['payment_proof_path'])) ?>" target="_blank" rel="noopener">Ver comprobante</a>
            </p>
        <?php else: ?>
            <p class="alert alert-warn">
                Elige cómo pagar: genera una CLABE SPEI (OpenPay se confirma solo) o, si ya pagaste en efectivo/transferencia, sube tu comprobante.
            </p>
        <?php endif; ?>

        <div class="stack" style="gap:1.25rem;margin-top:1rem">
            <div>
                <h3 style="margin:0 0 0.5rem">Opción 1 · SPEI OpenPay</h3>
                <?php if (!empty($item['openpay_clabe'])): ?>
                    <p>Datos SPEI:</p>
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
                    <form method="post" action="/alumno/caso/request-spei" class="actions" style="margin-top:0.5rem">
                        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
                        <button class="btn btn-ghost" type="submit">Reenviar instrucciones SPEI</button>
                    </form>
                <?php else: ?>
                    <p class="muted">Aún no tienes CLABE. Genera una para pagar por transferencia SPEI.</p>
                    <form method="post" action="/alumno/caso/request-spei" class="actions">
                        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
                        <button class="btn" type="submit">Generar CLABE SPEI</button>
                    </form>
                <?php endif; ?>
            </div>

            <div>
                <h3 style="margin:0 0 0.5rem">Opción 2 · Ya pagué (efectivo / transferencia)</h3>
                <p class="muted">
                    Sube el comprobante. No avanza el caso hasta que Doceo confirme la recepción del pago.
                </p>
                <form method="post" action="/alumno/caso/upload-payment-proof" enctype="multipart/form-data" class="stack form-grid">
                    <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
                    <label>Método
                        <select name="payment_method" required>
                            <option value="transfer" selected>Transferencia bancaria</option>
                            <option value="cash">Efectivo</option>
                            <option value="other">Otro</option>
                        </select>
                    </label>
                    <label>Nota (opcional)<input name="payment_note" placeholder="Ej. ref. 1234 / banco"></label>
                    <label class="field-wide">Comprobante (PDF o imagen)
                        <input type="file" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                    </label>
                    <div class="actions">
                        <button class="btn" type="submit"><?= $hasPaymentProof ? 'Reemplazar comprobante' : 'Subir comprobante' ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($paid): ?>
<?php
$moodle_enrolments = $moodle_enrolments ?? [];
$course_prorrogas = $course_prorrogas ?? [];
$prorrogaByEnrol = [];
foreach ($course_prorrogas as $pr) {
    if (in_array(($pr['status'] ?? ''), ['pending', 'proof_uploaded'], true)) {
        $prorrogaByEnrol[(int) ($pr['case_moodle_enrolment_id'] ?? 0)] = $pr;
    }
}
?>
<?php if ($hasMoodle || $moodle_enrolments): ?>
<section class="note student-stage" id="moodle">
    <h2>Curso de preparación (Moodle)</h2>
    <?php if ($hasMoodle): ?>
        <p>Credenciales de acceso:</p>
        <ul>
            <li><strong>Usuario:</strong> <code><?= e($item['moodle_user'] ?? '') ?></code></li>
            <?php if (!empty($item['moodle_password'])): ?>
                <li><strong>Contraseña:</strong> <code><?= e($item['moodle_password']) ?></code></li>
            <?php endif; ?>
        </ul>
        <p class="muted">Campus: <a href="https://campus.institutodoceo.com" target="_blank" rel="noopener">campus.institutodoceo.com</a></p>
    <?php endif; ?>

    <?php if ($moodle_enrolments): ?>
        <?php foreach ($moodle_enrolments as $enrol): ?>
            <?php
            $endsTs = strtotime((string) ($enrol['access_ends_at'] ?? '')) ?: 0;
            $isExpired = $endsTs > 0 && $endsTs < time();
            $status = (string) ($enrol['status'] ?? 'active');
            $pendingPr = $prorrogaByEnrol[(int) $enrol['id']] ?? null;
            $price = (float) ($enrol['prorroga_price'] ?? 0);
            ?>
            <div class="note" style="margin:0.85rem 0">
                <p style="margin:0 0 0.35rem">
                    <strong><?= e($enrol['course_name'] ?? $enrol['course_code'] ?? 'Curso') ?></strong>
                    · acceso hasta <strong><?= e($enrol['access_ends_at'] ?? '—') ?></strong>
                    <?php if ($isExpired || $status === 'expired'): ?>
                        <span class="pill pill-warn">Vencido</span>
                    <?php elseif ($status === 'active'): ?>
                        <span class="pill pill-ok">Activo</span>
                    <?php else: ?>
                        <span class="pill"><?= e($status) ?></span>
                    <?php endif; ?>
                </p>
                <?php if ($pendingPr): ?>
                    <p class="alert alert-warn">
                        Prórroga en curso (#<?= (int)$pendingPr['id'] ?>) · $<?= e(number_format((float)($pendingPr['amount'] ?? 0), 2)) ?> MXN
                        · estatus: <?= e($pendingPr['status'] ?? '') ?>
                        <?php if (($pendingPr['status'] ?? '') === 'proof_uploaded'): ?>
                            — Doceo confirmará tu comprobante.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($pendingPr['openpay_clabe'])): ?>
                        <ul>
                            <li><strong>CLABE:</strong> <code><?= e($pendingPr['openpay_clabe']) ?></code></li>
                            <li><strong>Banco:</strong> <?= e($pendingPr['openpay_bank'] ?? '') ?></li>
                            <li><strong>Referencia:</strong> <?= e($pendingPr['openpay_reference'] ?? '') ?></li>
                            <li><strong>Monto:</strong> $<?= e(number_format((float)($pendingPr['openpay_amount'] ?? $pendingPr['amount'] ?? 0), 2)) ?> MXN</li>
                        </ul>
                        <?php if (!empty($pendingPr['openpay_pdf_url'])): ?>
                            <p class="actions"><a class="btn btn-ghost" href="<?= e($pendingPr['openpay_pdf_url']) ?>" target="_blank" rel="noopener">PDF OpenPay</a></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="post" action="/alumno/caso/prorroga/request-spei" class="actions" style="margin:0.5rem 0">
                            <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
                            <input type="hidden" name="prorroga_id" value="<?= (int)$pendingPr['id'] ?>">
                            <button class="btn" type="submit">Generar CLABE SPEI</button>
                        </form>
                    <?php endif; ?>
                    <?php if (($pendingPr['status'] ?? '') !== 'paid'): ?>
                        <form method="post" action="/alumno/caso/prorroga/upload-proof" enctype="multipart/form-data" class="stack form-grid" style="margin-top:0.75rem">
                            <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
                            <input type="hidden" name="prorroga_id" value="<?= (int)$pendingPr['id'] ?>">
                            <label>Método
                                <select name="payment_method">
                                    <option value="transfer" selected>Transferencia</option>
                                    <option value="cash">Efectivo</option>
                                    <option value="other">Otro</option>
                                </select>
                            </label>
                            <label>Nota<input name="payment_note" placeholder="Opcional"></label>
                            <label class="field-wide">Comprobante<input type="file" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp" required></label>
                            <div class="actions"><button class="btn" type="submit">Subir comprobante de prórroga</button></div>
                        </form>
                    <?php endif; ?>
                <?php elseif ($price > 0): ?>
                    <p class="muted">
                        Puedes extender el acceso <strong>6 meses</strong> más por
                        <?= e(\App\Support\Str::money($price)) ?> (SPEI o comprobante).
                    </p>
                    <form method="post" action="/alumno/caso/prorroga/start" class="actions">
                        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
                        <input type="hidden" name="enrolment_id" value="<?= (int)$enrol['id'] ?>">
                        <button class="btn <?= ($isExpired || $status === 'expired') ? '' : 'btn-ghost' ?>" type="submit">
                            <?= ($isExpired || $status === 'expired') ? 'Pagar prórroga (+6 meses)' : 'Solicitar prórroga anticipada' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <p class="muted">Sin precio de prórroga configurado para este curso. Contacta a Doceo si necesitas extender el acceso.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php elseif ($hasMoodle): ?>
        <p class="muted">El acceso Moodle está limitado a 6 meses desde que se otorgó.</p>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="note student-stage <?= !$hasAccess ? 'student-stage-active' : '' ?>" id="examen">
    <h2>Acceso a tu examen</h2>
    <?php if ($hasAccess): ?>
        <ul>
            <?php if (!empty($item['folio_id'])): ?><li><strong>ID / Folio:</strong> <code><?= e($item['folio_id']) ?></code></li><?php endif; ?>
            <?php if (!empty($item['access_key'])): ?><li><strong>Clave / código:</strong> <code><?= e($item['access_key']) ?></code></li><?php endif; ?>
            <?php if (!empty($item['exam_date'])): ?><li><strong>Fecha:</strong> <?= e($item['exam_date']) ?><?= !empty($item['exam_time']) ? ' · ' . e($item['exam_time']) : '' ?></li><?php endif; ?>
            <?php if (!empty($item['reschedule_date'])): ?>
                <li><strong>Reagenda solicitada:</strong> <?= e($item['reschedule_date']) ?><?= !empty($item['reschedule_time']) ? ' · ' . e($item['reschedule_time']) : '' ?></li>
            <?php endif; ?>
            <?php if (!empty($item['zoom_url'])): ?><li><strong>Zoom:</strong> <a href="<?= e($item['zoom_url']) ?>" target="_blank" rel="noopener">Abrir enlace</a></li><?php endif; ?>
        </ul>
        <p class="muted">También te enviamos estos códigos por correo.</p>
    <?php else: ?>
        <p>
            Cuando tu pago esté confirmado y haya códigos en inventario, te asignaremos el Exam ID y la contraseña automáticamente.
            También puedes entrar a esta cuenta para revisar si ya fue asignado.
        </p>
        <?php if (!empty($item['exam_date'])): ?>
            <p class="muted">Fecha solicitada: <?= e($item['exam_date']) ?><?= !empty($item['exam_time']) ? ' · ' . e($item['exam_time']) : '' ?></p>
        <?php endif; ?>
        <?php if (!empty($item['reschedule_date'])): ?>
            <p class="muted">Reagenda pendiente: <?= e($item['reschedule_date']) ?><?= !empty($item['reschedule_time']) ? ' · ' . e($item['reschedule_time']) : '' ?></p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="note student-stage <?= ($hasAccess && !$hasResults && !$isInvalidated) ? 'student-stage-active' : '' ?>" id="resultados">
    <h2>Resultados del examen</h2>
    <?php if ($isInvalidated): ?>
        <p class="alert alert-warn">
            Tu examen fue marcado como <strong>invalidado</strong>.
            <?php if (!empty($item['invalidation_reason'])): ?>
                Motivo: <?= e($item['invalidation_reason']) ?>
            <?php endif; ?>
        </p>
    <?php elseif ($hasResults): ?>
        <ul>
            <?php if (!empty($item['results_url'])): ?>
                <li><a href="<?= e($item['results_url']) ?>" target="_blank" rel="noopener">Ver resultados</a></li>
            <?php endif; ?>
            <?php if (!empty($item['score_url'])): ?>
                <li><a href="<?= e($item['score_url']) ?>" target="_blank" rel="noopener">Score result</a></li>
            <?php endif; ?>
            <?php if (!empty($item['certificate_url'])): ?>
                <li><a href="<?= e($item['certificate_url']) ?>" target="_blank" rel="noopener">Certificate</a></li>
            <?php endif; ?>
        </ul>
    <?php else: ?>
        <p class="muted">Cuando el proveedor publique tus resultados, aparecerán aquí (y te avisaremos por correo).</p>
    <?php endif; ?>
</section>

<section class="note student-stage" id="reagenda">
    <h2>Solicitar reagenda</h2>
    <p class="muted">
        Si necesitas cambiar la fecha u hora, indícala aquí. Se notificará automáticamente al proveedor
        (y el equipo Doceo verá la solicitud en tu caso).
    </p>
    <form method="post" action="/alumno/caso/reschedule" class="stack form-grid">
        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
        <label>Nueva fecha<input type="date" name="reschedule_date" required value="<?= e($item['reschedule_date'] ?? '') ?>"></label>
        <label>Nueva hora<input type="time" name="reschedule_time" required value="<?= e(substr((string)($item['reschedule_time'] ?? $item['exam_time'] ?? '11:00'), 0, 5)) ?>"></label>
        <label class="field-wide">Motivo (opcional)<input name="reschedule_reason" placeholder="Ej. conflicto de trabajo"></label>
        <div class="actions"><button class="btn" type="submit">Solicitar reagenda</button></div>
    </form>
</section>

<section class="note student-stage" id="cenni">
    <h2>Certificado y trámite CENNI</h2>
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
        <?php if ($cenniStatus === 'docs_rejected' && !empty($item['cenni_notes'])): ?>
            <p class="alert alert-error">
                <strong>Documentos por corregir:</strong><br>
                <?= nl2br(e((string)$item['cenni_notes'])) ?>
            </p>
        <?php endif; ?>
        <?php if ($cenniStatus === 'issued'): ?>
            <div class="note">
                <?php if (!empty($item['cenni_folio'])): ?>
                    <p><strong>Folio:</strong> <?= e((string)$item['cenni_folio']) ?></p>
                <?php endif; ?>
                <?php if (!empty($item['student_curp'])): ?>
                    <p><strong>CURP:</strong> <?= e((string)$item['student_curp']) ?></p>
                <?php endif; ?>
                <?php if (!empty($item['cenni_download_url'])): ?>
                    <p><a class="btn" href="<?= e((string)$item['cenni_download_url']) ?>" target="_blank" rel="noopener">Descargar tu CENNI</a></p>
                <?php endif; ?>
                <?php if (!empty($item['cenni_sep_url'])): ?>
                    <p><a href="<?= e((string)$item['cenni_sep_url']) ?>" target="_blank" rel="noopener">Consulta oficial SEP</a></p>
                <?php endif; ?>
                <?php if (!empty($item['cenni_notes'])): ?>
                    <p class="muted"><?= nl2br(e((string)$item['cenni_notes'])) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($cenni_docs): ?>
            <div class="note" style="margin:0.75rem 0">
                <p><strong>Cómo llenar la solicitud CENNI</strong></p>
                <ul>
                    <?php foreach ($cenni_docs as $doc): ?>
                        <li>
                            <?php if (!empty($doc['file_path'])): ?>
                                <a href="/media?f=<?= e(rawurlencode((string)$doc['file_path'])) ?>&download=1&name=<?= e(rawurlencode((string)($doc['title'] ?? 'solicitud-cenni'))) ?>" target="_blank" rel="noopener">
                                    <?= e($doc['title'] ?? 'Instrucciones') ?>
                                </a>
                                <?php if (!empty($doc['version'])): ?> · v<?= e((string)$doc['version']) ?><?php endif; ?>
                            <?php else: ?>
                                <?= e($doc['title'] ?? 'Instrucciones') ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($cenniStatus !== 'issued'): ?>
        <form method="post" action="/alumno/caso/upload-cenni" enctype="multipart/form-data" class="stack">
            <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
            <label>INE (PDF/imagen)<input type="file" name="cenni_ine" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
            <label>CURP (PDF/imagen)<input type="file" name="cenni_curp" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
            <label>Solicitud CENNI firmada (PDF)<input type="file" name="cenni_solicitud" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
            <button class="btn" type="submit">Subir documentos</button>
        </form>
        <?php endif; ?>
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
